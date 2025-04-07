<?php

namespace Drupal\ai\Traits\File;

/**
 * Trait to add the possibility to store output base64 encoded strings.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateBase64Trait {

  /**
   * Generate base64 encoded string.
   *
   * @param string|null $data_url_scheme
   *   Add a data url scheme, like 'data:image/png'.
   *
   * @return string
   *   A base64 encoded string.
   */
  public function getAsBase64EncodedString(?string $data_url_scheme = NULL): string {
    $base64 = base64_encode($this->getBinary());
    if (!is_null($data_url_scheme)) {
      $base64 = $data_url_scheme . $base64;
    }
    elseif ($this->getMimeType()) {
      $base64 = 'data:' . $this->getMimeType() . ';base64,' . $base64;
    }

    return $base64;
  }

}
