<?php

namespace Drupal\easy_email\Service;

use Drupal\easy_email\Entity\EasyEmailInterface;

interface EmailUserEvaluatorInterface {

  /**
   * Evaluates the recipient user accounts for the entity email.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   */
  public function evaluateUsers(EasyEmailInterface $email);

}