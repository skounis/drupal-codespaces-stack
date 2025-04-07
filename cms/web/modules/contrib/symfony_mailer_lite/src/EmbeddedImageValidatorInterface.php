<?php

namespace Drupal\symfony_mailer_lite;

interface EmbeddedImageValidatorInterface {

  /**
   * @param EmbeddedImage $embedded_image
   * @param array $message
   *   The message array.
   * @return EmbeddedImage|bool
   *   The validated EmbeddedImage object or FALSE if the image is not valid.
   */
  public function validateEmbeddedImage(EmbeddedImage $embedded_image, array $message);

}
