<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a 'Set date for scheduled publishing' action.
 *
 * @RulesAction(
 *   id = "scheduler_set_publishing_date",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\RulesAction\SchedulerRulesActionDeriver"
 * )
 */
class SetPublishingDate extends SchedulerRulesActionBase {

  /**
   * Set the publish_on date on the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be scheduled for publishing.
   * @param int $date
   *   The date for publishing.
   */
  public function doExecute(EntityInterface $entity, $date) {
    $config = \Drupal::config('scheduler.settings');
    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    if ($entity->$bundle_field->entity->getThirdPartySetting('scheduler', 'publish_enable', $config->get('default_publish_enable'))) {
      $entity->set('publish_on', $date);
      // When this action is invoked and it operates on the entity being edited
      // then hook_entity_presave() will be executed automatically. But if this
      // action is being used to schedule a different entity then we need to
      // call the functions directly here.
      scheduler_entity_presave($entity);
    }
    else {
      // The action cannot be executed because the content type is not enabled
      // for scheduled publishing.
      $this->notEnabledWarning($entity, 'publish');
    }
  }

}
