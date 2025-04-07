<?php

namespace Drupal\dashboard;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Provides dynamic permissions.
 */
class DashboardPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of dashboard permissions.
   *
   * @return array
   *   The dashboard permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function permissions() {
    $permissions = [];
    $dashboards = Dashboard::loadMultiple();
    foreach ($dashboards as $dashboard) {
      $permissions["view {$dashboard->id()} dashboard"] = [
        'title' => $this->t('Access to %dashboard dashboard', ['%dashboard' => $dashboard->label()]),
        'dependencies' => [
          $dashboard->getConfigDependencyKey() => [$dashboard->getConfigDependencyName()],
        ],
      ];
    }

    return $permissions;
  }

}
