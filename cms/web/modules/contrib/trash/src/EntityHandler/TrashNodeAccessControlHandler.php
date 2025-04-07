<?php

namespace Drupal\trash\EntityHandler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;

/**
 * Overrides the node access control handler to check Trash access first.
 *
 * @see \Drupal\node\Entity\Node
 * @see \Drupal\node\NodeAccessControlHandler
 * @ingroup node_access
 */
class TrashNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    // Invoke the trash access checker before the 'bypass node access'
    // permission is checked by the parent implementation.
    $return = trash_entity_access($entity, $operation, $account);

    // Also execute the default access check except when the access result is
    // already forbidden, as in that case, it can not be anything else.
    if (!$return->isForbidden()) {
      $return = $return->orIf(parent::access($entity, $operation, $account, TRUE));
    }

    return $return_as_object ? $return : $return->isAllowed();
  }

}
