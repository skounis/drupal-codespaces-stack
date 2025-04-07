<?php

namespace Drupal\dashboard;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the dashboard entity type.
 *
 * @see \Drupal\dashboard\Entity\Dashboard
 */
class DashboardAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\dashboard\DashboardInterface $entity */
    if ($operation === 'view') {
      // Don't grant access to disabled dashboards.
      if (!$entity->status()) {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }

      // Ideally, user 1 should not be an exception and access to dashboards
      // would be based on specific permissions.
      // Once #540008 is in core we might have the opportunity to give a more
      // granular access to user 1. In the meantime, they will have access to
      // any published dashboard.
      return AccessResult::allowedIfHasPermission($account, "view {$entity->id()} dashboard");
    }

    if ($operation === 'preview') {
      $permissions = [
        $this->entityType->getAdminPermission(),
        "view {$entity->id()} dashboard",
      ];
      return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
