<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a 'Set date for scheduled unpublishing' action.
 *
 * @RulesAction(
 *   id = "scheduler_set_unpublishing_date",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\RulesAction\SchedulerRulesActionDeriver"
 * )
 */
class SetUnpublishingDate extends SchedulerRulesActionBase {

  /**
   * Set the unpublish_on date on the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be scheduled for unpublishing.
   * @param int $date
   *   The date for unpublishing.
   */
  public function doExecute(EntityInterface $entity, $date) {
    $config = \Drupal::config('scheduler.settings');
    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    if ($entity->$bundle_field->entity->getThirdPartySetting('scheduler', 'unpublish_enable', $config->get('default_unpublish_enable'))) {
      $entity->set('unpublish_on', $date);
      // When this action is invoked and it operates on the entity being edited
      // then hook_entity_presave() will be executed automatically. But if this
      // action is being used to schedule a different entity then we need to
      // call the functions directly here.
      scheduler_entity_presave($entity);
    }
    else {
      // The action cannot be executed because the content type is not enabled
      // for scheduled unpublishing.
      $this->notEnabledWarning($entity, 'unpublish');
    }
  }

}
