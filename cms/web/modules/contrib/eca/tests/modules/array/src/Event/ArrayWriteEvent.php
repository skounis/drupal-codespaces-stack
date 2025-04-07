<?php

namespace Drupal\eca_test_array\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides an array write event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package eca_test_array
 */
class ArrayWriteEvent extends Event {

  /**
   * The key that was written to the array.
   *
   * @var string
   */
  public string $key;

  /**
   * The value that was written to the array.
   *
   * @var string
   */
  public string $value;

  /**
   * Constructs a new ArrayWriteEvent object.
   *
   * @param string $key
   *   The key that was written to the array.
   * @param string $value
   *   The according value.
   */
  public function __construct(string $key, string $value) {
    $this->key = $key;
    $this->value = $value;
  }

}
