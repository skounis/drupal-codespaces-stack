<?php

namespace Drupal\easy_email;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Email entity.
 *
 * @see \Drupal\easy_email\Entity\EasyEmail.
 */
class EasyEmailAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view all email entities')
          ->orIf(
            AccessResult::allowedIfHasPermission($account, 'view own email entities')
              ->andIf(AccessResult::allowedIf($entity->getCreatorId() === $account->id())
              )
          );

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit email entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete email entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add email entities');
  }

}
