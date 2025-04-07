<?php

namespace Drupal\easy_email\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Event\EasyEmailEvent;
use Drupal\easy_email\Event\EasyEmailEvents;
use Html2Text\Html2Text;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailHandler implements EmailHandlerInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\easy_email\EasyEmailStorageInterface
   */
  protected $emailStorage;

  /**
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $emailTypeStorage;

  /**
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $emailViewBuilder;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * @var \Drupal\easy_email\Service\EmailTokenEvaluatorInterface
   */
  protected $tokenEvaluator;

  /**
   * @var \Drupal\easy_email\Service\EmailUserEvaluatorInterface
   */
  protected $userEvaluator;

  /**
   * @var \Drupal\easy_email\Service\EmailAttachmentEvaluatorInterface
   */
  protected $attachmentEvaluator;

  /**
   * @var array
   */
  protected $renderedPreviews;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * EmailHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\Component\Datetime\TimeInterface $time
   * @param \Drupal\easy_email\Service\EmailTokenEvaluatorInterface $tokenEvaluator
   * @param \Drupal\easy_email\Service\EmailUserEvaluatorInterface $userEvaluator
   * @param \Drupal\easy_email\Service\EmailAttachmentEvaluatorInterface $attachmentEvaluator
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MailManagerInterface $mailManager, LanguageManagerInterface $languageManager, RendererInterface $renderer, TimeInterface $time, EmailTokenEvaluatorInterface $tokenEvaluator, EmailUserEvaluatorInterface $userEvaluator, EmailAttachmentEvaluatorInterface $attachmentEvaluator, EventDispatcherInterface $eventDispatcher) {
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
    $this->emailStorage = $entityTypeManager->getStorage('easy_email');
    $this->emailViewBuilder = $entityTypeManager->getViewBuilder('easy_email');
    $this->emailTypeStorage = $entityTypeManager->getStorage('easy_email_type');
    $this->time = $time;
    $this->tokenEvaluator = $tokenEvaluator;
    $this->userEvaluator = $userEvaluator;
    $this->attachmentEvaluator = $attachmentEvaluator;
    $this->renderedPreviews = [];
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * @inheritDoc
   */
  public function createEmail($values = []) {
    return $this->emailStorage->create($values);
  }

  /**
   * @inheritDoc
   */
  public function duplicateExists(EasyEmailInterface $email) {
    if ($email->hasField('key') && ($key = $email->getKey())) {
      $email = $email->createDuplicate();
      $this->userEvaluator->evaluateUsers($email);
      $key = $this->tokenEvaluator->replaceTokens($email, $key);
      $result = $this->emailStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('key', $key)
        ->exists('sent')
        ->range(0, 1)
        ->execute();
      if (!empty($result)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function sendEmail(EasyEmailInterface $email, $params = [], $send_duplicate = FALSE, $save_email_entity = FALSE) {
    if ($email->isSent()) {
      return FALSE;
    }

    if (!$send_duplicate && $this->duplicateExists($email)) {
      return FALSE;
    }

    $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      // Don't clear tokens here because we need to check for unsafe tokens later.
      return $this->tokenEvaluator->evaluateTokens($email, FALSE);
    });

    $this->userEvaluator->evaluateUsers($email);

    $params = $this->generateEmailParams($email, $params);

    /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $email_type */
    $email_type = $this->emailTypeStorage->load($email->bundle());
    $save_attachments_to = FALSE;
    if ($email_type->getSaveAttachment()) {
      $save_attachments_to = $email_type->getAttachmentScheme() . '://' . $this->tokenEvaluator->replaceTokens($email, $email_type->getAttachmentDirectory());
    }

    $this->attachmentEvaluator->evaluateAttachments($email, $save_attachments_to);
    $params['attachments'] = $email->getEvaluatedAttachments();

    $reply = $email->getReplyToAddress();

    $recipient_emails = $email->getCombinedRecipientAddresses();

    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    if ($this->tokenEvaluator->containsUnsafeTokens($email)) {
      // We need to send to each recipient individually and make sure they only have their own unsafe token evaluated.
      $emails_to_send = $this->createUnsafeEmailsForRecipients($email, $params);
    }
    else {
      $emails_to_send = [
        [
          'to' => implode(', ', $recipient_emails),
          'email' => $email,
          'params' => $params,
        ]
      ];
    }

    // Now, let's evaluate tokens more time and clear any that are left unreplaced.
    $this->tokenEvaluator->evaluateTokens($email, TRUE);

    $return = [];
    foreach ($emails_to_send as $email_info) {
      if (!empty($email_info['to'])) {
        $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_PRESEND);
        $message = $this->mailManager->mail('easy_email', $email_info['email']->bundle(), $email_info['to'], $default_langcode, $email_info['params'], $reply, TRUE);
      }
      if (!empty($message['result'])) {
        $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_SENT);
        $email_info['email']->setSentTime($this->time->getCurrentTime());
      }
      if ($save_email_entity) {
        $email_info['email']->save();
      }
      $return[] = $email_info['email'];
    }

    return $return;
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param array $params
   *
   * @return array
   */
  protected function createUnsafeEmailsForRecipients(EasyEmailInterface $email, array $params) {
    $emails = [];
    $recipients = $email->getRecipients();
    foreach ($recipients as $recipient) {
      if (empty($emails)) {
        // The first email should use the original email object
        $user_email = $email;
      }
      else {
        $user_email = $email->createDuplicate();
      }
      $user_email->setRecipientIds([$recipient->id()]);
      $user_email->setRecipientAddresses([$recipient->getEmail()]);
      $user_email->setCCIds(NULL);
      $user_email->setCCAddresses(NULL);
      $user_email->setBCCIds(NULL);
      $user_email->setBCCAddresses(NULL);
      $user_params = $params;
      if (isset($user_params['headers']['Cc'])) {
        unset($user_params['headers']['Cc']);
      }
      if (isset($user_params['headers']['Bcc'])) {
        unset($user_params['headers']['Bcc']);
      }
      $unsafe_user_email = $user_email->createDuplicate();
      $html_body = $user_email->getHtmlBody();
      if (!empty($html_body)) {
        $unsafe_user_email->setHtmlBody($this->tokenEvaluator->replaceUnsafeTokens($html_body['value'], $recipient), $html_body['format']);
        $user_params['body'] = $this->buildHtmlBody($unsafe_user_email);
      }
      $plain_body = $user_email->getPlainBody();
      if (!empty($plain_body)) {
        $unsafe_user_email->setPlainBody($this->tokenEvaluator->replaceUnsafeTokens($plain_body, $recipient));
        $user_params['plain'] = $this->buildPlainBody($unsafe_user_email);
      }
      $emails[] = [
        'to' => $recipient->getEmail(),
        'email' => $user_email,
        'params' => $user_params,
      ];
    }
    return $emails;
  }

  /**
   * @inheritDoc
   */
  public function preview(EasyEmailInterface $email, $params = []) {
    $message = NULL;
    if (!$email->isNew() && isset($this->renderedPreviews[$email->id()])) {
      $message = $this->renderedPreviews[$email->id()];
    }
    if (empty($message)) {
      $this->tokenEvaluator->evaluateTokens($email, FALSE);

      $params = $this->generateEmailParams($email, $params);
      $params['easy_email_preview'] = TRUE;

      $reply = $email->getReplyToAddress();

      $recipient_emails = $email->getCombinedRecipientAddresses() ?? [];
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      $to = implode(', ', $recipient_emails);

      $message = $this->mailManager->mail('easy_email', $email->bundle(), $to, $default_langcode, $params, $reply, FALSE);
      if (!$email->isNew()) {
        $this->renderedPreviews[$email->id()] = $message;
      }
    }

    // Copy anything within the 'params' key up to the main array.
    if (!empty($message['params']) && is_array($message['params'])) {
      foreach ($message['params'] as $key => $value) {
        if (!isset($message[$key])) {
          $message[$key] = $value;
        }
      }
    }

    return $message;
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param array $params
   *
   * @return array
   */
  protected function generateEmailParams(EasyEmailInterface $email, $params = []) {

    $headers = [
      'Content-Transfer-Encoding' => '8Bit',
    ];

    $from = $email->getFromAddress();
    if (!empty($from) && !empty($email->getFromName())) {
      $from = $email->getFromName() . ' <' . $from . '>';
    }
    if (!empty($from)) {
      $headers += [
        'From' => $from,
      ];
    }

    // Determine which versions of the body text we need to make
    if ($email->hasField('body_html') && $email->hasField('body_plain')) {
      $headers['Content-Type'] = 'text/html; charset=UTF-8';

      if ($this->shouldGeneratePlainBody($email)) {
        // We have HTML and need generate plain body text.
        $body = $this->buildHtmlBody($email);
        $params['body'] = $body;
        $params['convert'] = TRUE;
      }
      else {
        // We have HTML and plain body text.
        $params['body'] = $this->buildHtmlBody($email);
        $params['plain'] = $this->buildPlainBody($email);
      }
    }
    elseif ($email->hasField('body_html')) {
      // We have only HTML body text
      $headers['Content-Type'] = 'text/html; charset=UTF-8';
      $params['body'] = $this->buildHtmlBody($email);
    }
    elseif ($email->hasField('body_plain')) {
      // We have only plain body text
      $headers['Content-Type'] = 'text/plain; charset=UTF-8';
      $params['body'] = $this->buildPlainBody($email); //$this->renderInNewContext(, TRUE);
    }
    else {
      // No body: ¯\_(ツ)_/¯
    }

    $cc = $email->getCombinedCCAddresses();
    if (!empty($cc)) {
      $headers['Cc'] = implode(', ', $cc);
    }

    $bcc = $email->getCombinedBCCAddresses();
    if (!empty($bcc)) {
      $headers['Bcc'] = implode(', ', $bcc);
    }

    if (!empty($params['headers'])) {
      $headers += $params['headers'];
    }

    $params += [
      'headers' => $headers,
      'subject' => $email->getSubject(),
    ];

    $params['easy_email'] = TRUE;

    return $params;
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return bool
   */
  protected function hasHTMLBody(EasyEmailInterface $email) {
    return $email->hasField('body_html') && !empty($email->getHtmlBody()) && !empty($email->getHtmlBody()['value']);
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return bool
   */
  protected function hasPlainBody(EasyEmailInterface $email) {
    return $email->hasField('body_plain') && !empty($email->getPlainBody());
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return bool
   */
  protected function hasInboxPreview(EasyEmailInterface $email) {
    return $email->hasField('inbox_preview') && !empty($email->getInboxPreview());
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return array
   */
  protected function buildHtmlBody(EasyEmailInterface $email) {
    $body = [
      'body' => [
        '#theme' => 'easy_email_body_html',
        '#easy_email' => $email,
      ],
    ];

    if ($this->hasInboxPreview($email)) {
      $body['inbox_preview'] = [
        '#theme' => 'easy_email_body_inbox_preview',
        '#easy_email' => $email,
        '#weight' => -100,
      ];
    }
    return $body;
  }

  /**
   * @param array $build
   *
   * @return string
   */
  protected function renderInNewContext($build, $plain_text = FALSE) {
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($build, $plain_text) {
      if ($plain_text) {
        return PlainTextOutput::renderFromHtml($this->renderer->renderPlain($build));
      }
      return $this->renderer->render($build);
    });
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return array
   */
  protected function buildPlainBody(EasyEmailInterface $email) {
    return [
      '#theme' => 'easy_email_body_plain',
      '#easy_email' => $email,
    ];
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return bool
   */
  protected function shouldGeneratePlainBody(EasyEmailInterface $email) {
    /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $email_type */
    $email_type = $this->emailTypeStorage->load($email->bundle());
    return $email_type->getGenerateBodyPlain();
  }

}
