<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Deletes a content entity.
 *
 * @Action(
 *   id = "eca_delete_entity",
 *   label = @Translation("Entity: delete"),
 *   description = @Translation("Deletes an existing content entity."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class DeleteEntity extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    if (!($object instanceof ContentEntityInterface) || $object->isNew()) {
      $access_result = AccessResult::forbidden();
    }
    else {
      $access_result = $object->access('delete', $account, TRUE);
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface) || $entity->isNew()) {
      return;
    }
    $entity->delete();
  }

}
