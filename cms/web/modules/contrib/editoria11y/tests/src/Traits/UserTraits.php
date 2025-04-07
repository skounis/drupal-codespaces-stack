<?php

namespace Drupal\Tests\editoria11y\Traits;

/**
 * Traits the new user.
 */
trait UserTraits {

  /**
   * Views aggregation causes intractable incorrect schema errors.
   *
   * @var bool
   *
   * @see \Drupal\Core\Config\Development\ConfigSchemaChecker
   */
  protected bool $strictConfigSchema = FALSE;

  /**
   * Define a new administrator user.
   */
  public function setUpAdmin() : void {
    // $user =
  }

}
