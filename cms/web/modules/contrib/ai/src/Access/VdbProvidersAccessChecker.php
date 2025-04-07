<?php

declare(strict_types=1);

namespace Drupal\ai\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai\AiVdbProviderPluginManager;

/**
 * Checks if user has permission and vdb providers exist.
 */
final class VdbProvidersAccessChecker implements AccessInterface {

  /**
   * Constructs a VdbProvidersAccessChecker object.
   */
  public function __construct(
    private readonly AiVdbProviderPluginManager $aiVdbProvider,
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
    $providers = $this->aiVdbProvider->getProviders();

    $result = AccessResult::allowedIf(!empty($providers) && $account->hasPermission('administer ai'));
    $result->addCacheableDependency($account);
    $result->addCacheContexts(['ai_providers']);

    return $result;
  }

}
