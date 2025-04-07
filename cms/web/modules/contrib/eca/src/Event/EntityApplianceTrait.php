<?php

namespace Drupal\eca\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Trait for events that check for appliance of an entity.
 *
 * @internal
 *   This trait is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
trait EntityApplianceTrait {

  /**
   * Checks whether the given entity applies for the given arguments.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for.
   * @param array $arguments
   *   Configured arguments.
   *
   * @return bool
   *   Returns TRUE when it applies, FALSE otherwise.
   */
  protected function appliesForEntityTypeOrBundle(EntityInterface $entity, array $arguments): bool {
    if (!empty($arguments['entity_type_id']) && $arguments['entity_type_id'] !== '*') {
      $contains_entity_type_id = FALSE;
      foreach (explode(',', $arguments['entity_type_id']) as $c_entity_type_id) {
        $c_entity_type_id = strtolower(trim($c_entity_type_id));
        if ($contains_entity_type_id = ($c_entity_type_id === $entity->getEntityTypeId())) {
          break;
        }
      }
      if (!$contains_entity_type_id) {
        return FALSE;
      }
    }

    if (!empty($arguments['bundle']) && $arguments['bundle'] !== '*') {
      $contains_bundle = FALSE;
      foreach (explode(',', $arguments['bundle']) as $c_bundle) {
        $c_bundle = strtolower(trim($c_bundle));
        if ($contains_bundle = ($c_bundle === $entity->bundle())) {
          break;
        }
      }
      if (!$contains_bundle) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
