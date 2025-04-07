<?php

namespace Drupal\easy_email\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\easy_email\Entity\EasyEmailInterface;

interface EmailTokenEvaluatorInterface {

  /**
   * Evaluate the tokens in entity email fields.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   */
  public function evaluateTokens(EasyEmailInterface $email, bool $clear = TRUE);

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param array|string $values
   * @param bool $unique
   *
   * @return array|string
   */
  public function replaceTokens(EasyEmailInterface $email, $values, $unique = FALSE, bool $clear = TRUE);

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *
   * @return bool
   */
  public function containsUnsafeTokens(EasyEmailInterface $email);

  /**
   * @param string $text
   * @param \Drupal\Core\Session\AccountInterface $recipient
   *
   * @return string
   */
  public function replaceUnsafeTokens($text, AccountInterface $recipient);

}
