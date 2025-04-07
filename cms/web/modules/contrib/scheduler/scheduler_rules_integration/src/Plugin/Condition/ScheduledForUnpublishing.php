<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rules\Core\RulesConditionBase;

/**
 * Provides 'Entity is scheduled for publishing' condition.
 *
 * @Condition(
 *   id = "scheduler_entity_is_scheduled_for_unpublishing",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\Condition\ConditionDeriver"
 * )
 */
class ScheduledForUnpublishing extends RulesConditionBase {

  /**
   * Determines whether an entity is scheduled for unpublishing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be checked.
   *
   * @return bool
   *   TRUE if the entity is scheduled for unpublishing, FALSE if not.
   */
  public function doEvaluate(EntityInterface $entity) {
    return isset($entity->unpublish_on->value);
  }

}
