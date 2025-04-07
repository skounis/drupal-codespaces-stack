<?php

namespace Drupal\easy_email;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the email type entity.
 *
 * @see \Drupal\easy_email\Entity\EasyEmailType
 */
class EasyEmailTypeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $parentAccess = parent::checkAccess($entity, $operation, $account);

    switch ($operation) {
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit email types')->orIf($parentAccess);

      case 'preview':
        return AccessResult::allowedIfHasPermission($account, 'preview email types')->orIf($parentAccess);

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete email types')->orIf($parentAccess);
    }

    return $parentAccess;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create email types')
      ->orIf(parent::checkCreateAccess($account, $context, $entity_bundle));
  }

}
