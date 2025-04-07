<?php

namespace Drupal\eca_ui\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for ECA config entities.
 *
 * @see \Drupal\eca\Entity\Eca
 */
class AccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\eca\Entity\Eca $entity */

    // Deny access for ECA configurations that cannot be edited.
    if ($operation === 'update' && !$entity->isEditable()) {
      return AccessResult::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
