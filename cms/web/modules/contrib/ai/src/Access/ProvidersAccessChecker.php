<?php

declare(strict_types=1);

namespace Drupal\ai\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai\AiProviderPluginManager;

/**
 * Checks if user has permission and providers exist.
 */
final class ProvidersAccessChecker implements AccessInterface {

  /**
   * Constructs a ProvidersAccessChecker object.
   */
  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
  ) {}

  /**
   * Checks whether the user has the correct permission and providers exist.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   the access check result.
   */
  public function access(AccountInterface $account): AccessResult {
    $providers = $this->aiProvider->getDefinitions();

    $result = AccessResult::allowedIf(!empty($providers) && $account->hasPermission('administer ai'));
    $result->addCacheableDependency($account);
    $result->addCacheContexts(['ai_providers']);

    return $result;
  }

}
