<?php

namespace Drupal\easy_email\Service;

use Drupal\easy_email\Entity\EasyEmailInterface;

interface EmailHandlerInterface {

  /**
   * Create a new email entity
   *
   * @param array $values
   *   The initial values for the entity email.
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   */
  public function createEmail($values = []);

  /**
   * Check if a duplicate for this email exists by unique key.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *   The email entity to check
   *
   * @return bool
   *   TRUE if a matching email exists, FALSE otherwise.
   */
  public function duplicateExists(EasyEmailInterface $email);

  /**
   * Sends an email entity.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *   The email entity to send
   * @param array $params
   *   The initial params array
   * @param bool $send_duplicate
   *   Send email even if another email with the same unique key has been sent. (default = false)
   * @param bool $save_email_entity
   *    Save the email entity after sending. (default = false)
   *
   * @return EasyEmailInterface[]
   *   An array of emails from the send operation.
   *   There may be more than one result if there are multiple recipients and
   *   unsafe tokens are used. Check the email entities ->isSent() method to
   *   determine if the email was successfully sent.
   */
  public function sendEmail(EasyEmailInterface $email, $params = [], $send_duplicate = FALSE, $save_email_entity = FALSE);

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param array $params
   *
   * @return array
   */
  public function preview(EasyEmailInterface $email, $params = []);

}
