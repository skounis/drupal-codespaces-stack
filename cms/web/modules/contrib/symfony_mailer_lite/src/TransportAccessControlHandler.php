<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class TransportAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $parentAccess = parent::checkAccess($entity, $operation, $account);

    switch ($operation) {
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer symfony_mailer_lite configuration')
          ->andIf(AccessResult::allowedIf(!$entity->isDefault()))
          ->andIf($parentAccess);
    }

    return $parentAccess;
  }

}
