<?php

namespace Drupal\easy_email\Service;

use Drupal\easy_email\Entity\EasyEmailInterface;

interface EmailAttachmentEvaluatorInterface {

  /**
   * Evaluate the attachments in entity email fields.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *   The email entity
   * @param string|bool $save_attachment_to
   *   URI of the destination directory to save the dynamic file attachments,
   *   FALSE to bypass saving dynamic attachments.
   *
   */
  public function evaluateAttachments(EasyEmailInterface $email, $save_attachment_to = FALSE);

}