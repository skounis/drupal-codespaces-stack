<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Remove the default dashboard tab, so it is not duplicated.
 */
class MenuLocalTasks {

  public function __construct(protected AccountInterface $account, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Remove the default dashboard tab, so it is not duplicated.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, string $route_name, RefinableCacheableDependencyInterface &$cacheability) {
    $storage = $this->entityTypeManager->getStorage('dashboard');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->sort('weight', 'ASC');
    $dashboard_ids = $query->execute();
    $default = NULL;
    foreach ($dashboard_ids as $dashboard_id) {
      $dashboard = $storage->load($dashboard_id);
      if ($dashboard->access('view', $this->account)) {
        $default = $dashboard->id();
        break;
      }
    }

    if ($default !== NULL && isset($data['tabs'][0]['dashboard.dashboards:dashboard.' . $default])) {
      unset($data['tabs'][0]['dashboard.dashboards:dashboard.' . $default]);
    }
    // The tab we are removing depends on the user's access to see dashboard.
    $cacheability->addCacheContexts(['user.permissions']);
    // The tab we're removing depends on the dashboard list.
    $cacheability->addCacheTags(['dashboard_list']);
  }

}
