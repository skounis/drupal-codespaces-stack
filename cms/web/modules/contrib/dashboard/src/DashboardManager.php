<?php

namespace Drupal\dashboard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Dashboard manager service.
 */
class DashboardManager {

  /**
   * Constructs a new DashboardManager instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns the default dashboard entity for the current user if available.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (Optional) The account to get the dashboard. Current user by default.
   *
   * @return \Drupal\dashboard\DashboardInterface|null
   *   The dashboard to be used if available, NULL otherwise.
   */
  public function getDefaultDashboard(?AccountInterface $account = NULL) {
    $account ??= $this->currentUser;
    $storage = $this->entityTypeManager->getStorage('dashboard');
    $dashboard_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->sort('weight', 'ASC')
      ->execute();

    foreach ($dashboard_ids as $dashboard_id) {
      $dashboard = $storage->load($dashboard_id);
      if ($dashboard->access('view', $account)) {
        return $dashboard;
      }
    }

    return NULL;
  }

}
