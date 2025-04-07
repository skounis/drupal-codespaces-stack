<?php

namespace Drupal\easy_email\Service;


use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Event\EasyEmailEvent;
use Drupal\easy_email\Event\EasyEmailEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailTokenEvaluator implements EmailTokenEvaluatorInterface {

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs the EmailTokenEvaluator
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   * @param \Drupal\Core\Utility\Token $token
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, Token $token) {
    $this->token = $token;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * @inheritDoc
   */
  public function evaluateTokens(EasyEmailInterface $email, bool $clear = TRUE) {
    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_PRETOKENEVAL);

    if ($email->hasField('key')) {
      $email->setKey($this->replaceTokens($email, $email->getKey(), TRUE, $clear));
    }
    $email->setRecipientAddresses($this->replaceTokens($email, $email->getRecipientAddresses(), TRUE, $clear));
    $email->setCCAddresses($this->replaceTokens($email, $email->getCCAddresses(), TRUE, $clear));
    $email->setBCCAddresses($this->replaceTokens($email, $email->getBCCAddresses(), TRUE, $clear));
    $email->setFromName($this->replaceTokens($email, $email->getFromName(), TRUE, $clear));
    $email->setFromAddress($this->replaceTokens($email, $email->getFromAddress(), TRUE, $clear));
    $email->setReplyToAddress($this->replaceTokens($email, $email->getReplyToAddress(), TRUE, $clear));
    $email->setSubject($this->replaceTokens($email, $email->getSubject(), TRUE, $clear));
    if ($email->hasField('body_html')) {
      $html_body = $email->getHtmlBody();
      $email->setHtmlBody($this->replaceTokens($email, $html_body['value'], TRUE, $clear), $html_body['format']);
    }
    if ($email->hasField('body_plain')) {
      $email->setPlainBody($this->replaceTokens($email, $email->getPlainBody(), TRUE, $clear));
    }
    if ($email->hasField('inbox_preview')) {
      $email->setInboxPreview($this->replaceTokens($email, $email->getInboxPreview(), TRUE, $clear));
    }
    if ($email->hasField('attachment_path')) {
      $email->setAttachmentPaths($this->replaceTokens($email, $email->getAttachmentPaths(), TRUE, $clear));
    }

    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_TOKENEVAL);
  }

  public function containsUnsafeTokens(EasyEmailInterface $email) {
    $tokens = [];
    if ($email->hasField('body_html')) {
      $html_body = $email->getHtmlBody();
      $body_tokens = $this->token->scan($html_body['value']);
      if (!empty($body_tokens['easy_email'])) {
        $tokens = array_merge($tokens, $body_tokens['easy_email']);
      }
    }
    if ($email->hasField('body_plain')) {
      $body_tokens = $this->token->scan($email->getPlainBody() ?? '');
      if (!empty($body_tokens['easy_email'])) {
        $tokens = array_merge($tokens, $body_tokens['easy_email']);
      }
    }
    foreach($tokens as $token) {
      if (preg_match('/:one-time-login-url\]$/', $token) || preg_match('/:cancel-url\]$/', $token)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @return array
   */
  protected function unsafeTokens() {
    return [
      'one-time-login-url',
      'cancel-url'
    ];
  }

  /**
   * @inheritDoc
   */
  public function replaceTokens(EasyEmailInterface $email, $values, $unique = FALSE, $clear = TRUE) {
    // @todo Add strict type hinting on array|string and don't do this work in
    //   the NULL $values case.
    if ($values === NULL) {
      return NULL;
    }
    if (is_array($values)) {
      $replaced = [];
      foreach ($values as $key => $value) {
        $replaced[$key] = $this->token->replace($value, ['easy_email' => $email], ['clear' => $clear]);
      }
      if ($unique) {
        $replaced = array_unique($replaced);
      }
      return $replaced;
    }
    return $this->token->replace($values, ['easy_email' => $email], ['clear' => $clear]);
  }


  /**
   * @inheritDoc
   */
  public function replaceUnsafeTokens($text, AccountInterface $recipient) {
    $unsafe_tokens = $this->unsafeTokens();
    $tokens = $this->token->scan($text);
    if (!empty($tokens['easy_email'])) {
      foreach ($tokens['easy_email'] as $token => $full_token) {
        $token_parts = explode(':', $token);
        $final_token = array_pop($token_parts);
        if (in_array($final_token, $unsafe_tokens)) {
          $text = str_replace($full_token, '[user:' . $final_token . ']', $text);
        }
      }
    }
    return $this->token->replace($text, ['user' => $recipient], ['callback' => 'user_mail_tokens']);
  }

}
