<?php

namespace Drupal\eca_test_array\Event;

/**
 * Defines events provided by the ECA test module for basic plugins.
 */
final class ArrayEvents {

  /**
   * Dispatches an event when into a static array is being written.
   *
   * @Event
   *
   * @var string
   */
  public const WRITE = 'eca_test_array.array_write';

}
