<?php

namespace Drupal\ai\Traits\File;

use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;

/**
 * Trait to add the possibility to get the file mime type guesser.
 *
 * @package Drupal\ai\Traits\File
 */
trait FileMimeTypeTrait {

  /**
   * {@inheritdoc}
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesser {
    return \Drupal::service('file.mime_type.guesser');
  }

}
