<?php

namespace Drupal\ai\OperationType;

/**
 * Base Input Interface class.
 */
interface InputInterface {

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function toString(): string;

}
