<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a 'Publish immediately' action.
 *
 * @RulesAction(
 *   id = "scheduler_publish_now",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\RulesAction\SchedulerRulesActionDeriver"
 * )
 */
class PublishNow extends SchedulerRulesActionBase {

  /**
   * Set the entity status to Published.
   *
   * This action is provided by the Rules Module but only for node content, not
   * Media. There is also a problem with recursion in the Rules action due to
   * autoSaveContext(). Hence better for Scheduler to provide this action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be published.
   */
  public function doExecute(EntityInterface $entity) {
    $entity->setPublished();
  }

}
