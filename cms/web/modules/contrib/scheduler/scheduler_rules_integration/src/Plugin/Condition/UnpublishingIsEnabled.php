<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rules\Core\RulesConditionBase;

/**
 * Provides 'Unpublishing is enabled for the type of this entity' condition.
 *
 * @Condition(
 *   id = "scheduler_unpublishing_is_enabled",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\Condition\ConditionDeriver"
 * )
 */
class UnpublishingIsEnabled extends RulesConditionBase {

  /**
   * Determines whether scheduled unpublishing is enabled for this entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be checked.
   *
   * @return bool
   *   TRUE if scheduled unpublishing is enabled for the bundle of this entity
   *   type.
   */
  public function doEvaluate(EntityInterface $entity) {
    $config = \Drupal::config('scheduler.settings');
    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    return ($entity->$bundle_field->entity->getThirdPartySetting('scheduler', 'unpublish_enable', $config->get('default_unpublish_enable')));
  }

}
