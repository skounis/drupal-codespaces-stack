<?php

namespace Drupal\eca_user;

use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\eca_user\Plugin\Action\SwitchAccount;
use Drupal\user\UserInterface;

/**
 * Service with a stack of switched accounts that can be properly restored.
 */
class AccountSwitcher {

  /**
   * The stack of switched accounts.
   *
   * @var \Drupal\eca_user\Plugin\Action\SwitchAccount[]
   */
  protected array $actions = [];

  /**
   * Constructs the ECA account switcher servicer with a stack.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The Drupal core account switcher service.
   */
  public function __construct(
    protected readonly AccountSwitcherInterface $accountSwitcher,
  ) {}

  /**
   * Switch to another user account.
   *
   * @param \Drupal\eca_user\Plugin\Action\SwitchAccount $action
   *   The action plugin which executes the account switch. That plugin will
   *   be put on the stack so that the CleanupInterface can later reliably
   *   revert to the user prior to this action.
   * @param \Drupal\user\UserInterface|null $user
   *   The user account to switch to.
   */
  public function switchTo(SwitchAccount $action, ?UserInterface $user = NULL): void {
    if ($user === NULL) {
      return;
    }
    $this->actions[] = $action;
    $this->accountSwitcher->switchTo($user);
  }

  /**
   * Cleans up the account switch if required.
   *
   * The switch back is only required if the top action on the stack is the same
   * plugin as the one that wants to cleanup. If it's not, then an explicit
   * switch back has been performed by the SwitchBack action plugin.
   *
   * @param \Drupal\eca_user\Plugin\Action\SwitchAccount $action
   *   The action plugin that asks for the cleanup.
   */
  public function cleanup(SwitchAccount $action): void {
    if (end($this->actions) === $action) {
      $this->switchBack();
    }
  }

  /**
   * Perform a switch back.
   *
   * This will only happen, when there's still an action plugin on the stack.
   */
  public function switchBack(): void {
    if (!empty($this->actions)) {
      $this->accountSwitcher->switchBack();
      array_pop($this->actions);
    }
  }

}
