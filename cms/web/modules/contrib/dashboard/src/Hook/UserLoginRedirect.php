<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\dashboard\DashboardRedirectHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Redirect to the dashboard after login if there is an applicable dashboard.
 */
#[Hook('user_login')]
class UserLoginRedirect {

  public function __construct(
    #[Autowire(service: 'dashboard.user_redirect')]
    protected DashboardRedirectHandler $redirectHandler,
  ) {}

  /**
   * Implements hook_user_login().
   */
  public function __invoke(AccountInterface $account): void {
    $this->redirectHandler->userLogin($account);
  }

}
