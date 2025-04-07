<?php

namespace Drupal\eca_user;

use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\BaseHookHandler;
use Drupal\user\UserInterface;

/**
 * The handler for hook implementations within the eca_user.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * Triggers the event for when a user logs into a new session.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account for the login event.
   */
  public function login(UserInterface $account): void {
    $this->triggerEvent->dispatchFromPlugin('user:login', $account);
  }

  /**
   * Triggers event for when a user logs out from the current session.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account that logs out.
   */
  public function logout(AccountInterface $account): void {
    $this->triggerEvent->dispatchFromPlugin('user:logout', $account);
  }

  /**
   * Triggers event when a user account gets canceled.
   *
   * @param array $edit
   *   The edit form being used for canceling the user account.
   * @param \Drupal\user\UserInterface $account
   *   The user account which gets canceled.
   * @param string $method
   *   The method selected for user cancellation.
   */
  public function cancel(array $edit, UserInterface $account, string $method): void {
    $this->triggerEvent->dispatchFromPlugin('user:cancel', $account);
  }

}
