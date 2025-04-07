<?php

namespace Drupal\eca\Event;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for events that involve a user account.
 */
interface AccountEventInterface {

  /**
   * Get the user account.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The user account.
   */
  public function getAccount(): AccountInterface;

}
