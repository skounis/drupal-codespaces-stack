<?php

namespace Drupal\easy_email_override\Service;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxHeader;

/**
 * Class MailManager.
 *
 * Decorates the MailManager::mail method to apply Easy Email overrides.
 *
 * @package Drupal\easy_email
 */
class MailManager extends DefaultPluginManager implements MailManagerInterface {

  /**
   * Decorated service object.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $decorated;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs the EmailManager object.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $decorated
   * @param \Traversable $namespaces
   * @param ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(MailManagerInterface $decorated, \Traversable $namespaces, ModuleHandlerInterface $module_handler, RendererInterface $renderer, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    parent::__construct('Plugin/Mail', $namespaces, $module_handler, 'Drupal\Core\Mail\MailInterface', 'Drupal\Core\Annotation\Mail');
    $this->decorated = $decorated;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
  }

  /**
   * @inheritDoc
   */
  public function getInstance(array $options) {
    return $this->decorated->getInstance($options);
  }

  /**
   * @inheritDoc
   */
  public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {
    // To avoid infinite recursion, we can't override any easy_email generated emails.
    // Just do a normal Drupal send for those.
    if ($module === 'easy_email') {
      return $this->decorated->mail($module, $key, $to, $langcode, $params, $reply, $send);
    }

    $easy_emails = [];
    $email_handler = \Drupal::service('easy_email.handler');
    $override_storage = \Drupal::entityTypeManager()->getStorage('easy_email_override');

    // Let's find any overrides for this email.

    // First, load any overrides that match exactly the module and key
    /** @var \Drupal\easy_email_override\Entity\EmailOverrideInterface[] $email_overrides */
    $email_overrides = $override_storage
      ->loadByProperties([
        'module' => $module,
        'key' => $key,
      ]);

    // If there aren't exact matches, look for matches for any email for the given module.
    // '*' is the key we're using for matching all
    if (empty($email_overrides)) {
      $email_overrides = $override_storage
        ->loadByProperties([
          'module' => $module,
          'key' => '*',
        ]);
    }

    // If we still don't have override matches, check if there's a global override.
    if (empty($email_overrides)) {
      $email_overrides = $override_storage
        ->loadByProperties([
          'module' => '*',
          'key' => '*',
        ]);
    }

    // If there are still no overrides, send the email normally.
    if (empty(($email_overrides))) {
      return $this->decorated->mail($module, $key, $to, $langcode, $params, $reply, $send);
    }

    $message = $this->buildMessageArray($module, $key, $to, $langcode, $params, $reply);

    // If we find more than one override for a given module/key combo, we'll send them all.
    // Not sure if that will be useful, but perhaps.
    foreach ($email_overrides as $email_override) {
      $email_template = $email_override->getEasyEmailType();
      $email = $email_handler->createEmail([
        'type' => $email_template,
      ]);
      $param_map = $email_override->getParamMap();
      foreach ($param_map as $pm) {
        if (!empty($pm['destination'])) {
          $email->set($pm['destination'], $params[$pm['source']]);
        }
      }

      $copied_fields = $email_override->getCopiedFields();
      if (!empty($message['headers'])) {
        $lowercase_headers = array_change_key_case($message['headers'], CASE_LOWER);
      }
      else {
        $lowercase_headers = [];
      }

      if (!empty($copied_fields['to'])) {
        $recipient_addresses = explode(',', $to);
        $recipient_addresses = array_map('trim', $recipient_addresses);
        $email->setRecipientAddresses($recipient_addresses);
      }
      if (!empty($copied_fields['from'])) {
        if (!empty($message['headers']['From'])) {
          if (preg_match('/(.*)\s+<(.+)>/', $lowercase_headers['from'], $matches)) {
            $email->setFromName(trim($matches[1]));
            $email->setFromAddress(trim($matches[2]));
          }
          else {
            $email->setFromAddress($lowercase_headers['from']);
          }
        }
        elseif (!empty($lowercase_headers['sender'])) {
          $email->setFromAddress($lowercase_headers['sender']);
        }
        elseif (!empty($message['from'])) {
          $email->setFromAddress($message['from']);
        }
      }
      if (!empty($copied_fields['reply_to']) && !empty($lowercase_headers['reply-to'])) {
        $email->setReplyToAddress($lowercase_headers['reply-to']);
      }
      if (!empty($copied_fields['cc']) && !empty($lowercase_headers['cc'])) {
        $email->setCcAddresses(explode(',', $lowercase_headers['cc']));
      }
      if (!empty($copied_fields['bcc']) && !empty($lowercase_headers['bcc'])) {
        $email->setBccAddresses(explode(',', $lowercase_headers['bcc']));
      }
      if (!empty($copied_fields['subject'])) {
        $email->setSubject($message['subject']);
      }

      $message_content_type = $this->getContentTypeFromMessage($message);
      if (!empty($copied_fields['body_html'])) {
        $html_body = $email->getHtmlBody();
        if ($message_content_type === 'text/html') {
          $email->setHtmlBody($message['body'], $html_body['format']);
        }
        else {
          $body = nl2br($message['body']);
          $email->setHtmlBody($body, $html_body['format']);
        }
      }
      if (!empty($copied_fields['body_plain']) && $message_content_type === 'text/plain') {
        $email->setPlainBody($message['body']);
      }
      if (!empty($copied_fields['attachments']) && isset($params['attachments']) && is_array($params['attachments'])) {
        $attachment_paths = [];
        foreach ($params['attachments'] as $attachment) {
          if (!empty($attachment['filepath'])) {
            $attachment_paths[] = $attachment['filepath'];
          }
        }
        $email->setAttachmentPaths($attachment_paths);
      }

      $result = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email_handler, $email) {
        $sent_emails = $email_handler->sendEmail($email);
        /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type */
        $easy_email_type = $this->entityTypeManager->getStorage('easy_email_type')->load($email->bundle());
        if ($easy_email_type->getSaveEmail()) {
          $email->save();
        }
        $first_sent_email = reset($sent_emails);
        return $first_sent_email->isSent();
      });
    }

    $message['result'] = TRUE;
    return $message;
  }

  protected function getContentTypeFromMessage($message) {
    $content_type_header = NULL;
    // Headers end up under params with Symfony Mailer.
    if (!empty($message['headers']['Content-Type'])) {
      $content_type_header = $message['headers']['Content-Type'];
    }
    if (str_contains($content_type_header, 'text/html')) {
      return 'text/html';
    }
    return 'text/plain';
  }

  protected function buildMessageArray($module, $key, $to, $langcode, $params = [], $reply = NULL) : array {
    // This is adapted from: Drupal\Core\Mail\MailManager::doMail()
    // We can't use method directly if we want to support generic email overrides
    // because it ends up formatting the email twice. This version builds the message
    // array without theme formatting. That will happen when we send the easy email.
    // @see: Drupal\Core\Mail\MailManager::doMail()
    $site_config = $this->configFactory->get('system.site');
    $site_mail = $site_config->get('mail');
    // Bundle up the variables into a structured array for altering.
    $message = [
      'id' => $module . '_' . $key,
      'module' => $module,
      'key' => $key,
      'to' => $to,
      'from' => $site_mail,
      'reply-to' => $reply,
      'langcode' => $langcode,
      'params' => $params,
      'send' => TRUE,
      'subject' => '',
      'body' => [],
    ];

    // Build the default headers.
    $headers = [
      'MIME-Version' => '1.0',
      'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
      'Content-Transfer-Encoding' => '8Bit',
      'X-Mailer' => 'Drupal',
    ];
    // To prevent email from looking like spam, the addresses in the Sender and
    // Return-Path headers should have a domain authorized to use the
    // originating SMTP server.
    $headers['From'] = $headers['Sender'] = $headers['Return-Path'] = $site_mail;
    // Make sure the site-name is a RFC-2822 compliant 'display-name'.
    if ($site_mail) {
      $mailbox = new MailboxHeader('From', new Address($site_mail, $site_config->get('name') ?: ''));
      $headers['From'] = $mailbox->getBodyAsString();
    }
    if ($reply) {
      $headers['Reply-to'] = $reply;
    }
    $message['headers'] = $headers;
    // Build the email (get subject and body, allow additional headers) by
    // invoking hook_mail() on this module.
    $this->moduleHandler->invoke($module, 'mail', [$key, &$message, $params]);

    // Invoke hook_mail_alter() to allow all modules to alter the resulting
    // email.
    $this->moduleHandler->alter('mail', $message);
    // Attempt to convert relative URLs to absolute.
    foreach ($message['body'] as &$body_part) {
      if ($body_part instanceof MarkupInterface) {
        $body_part = Markup::create((string) $body_part);
      }
    }
    $message['body'] = implode("\n\n", $message['body']);
    return $message;
  }

}
