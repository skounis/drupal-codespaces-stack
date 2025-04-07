<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an 'Unpublish immediately' action.
 *
 * @RulesAction(
 *   id = "scheduler_unpublish_now",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\RulesAction\SchedulerRulesActionDeriver"
 * )
 */
class UnpublishNow extends SchedulerRulesActionBase {

  /**
   * Set the entity status to Unpublished.
   *
   * This action is provided by the Rules Module but only for node content, not
   * Media. There is also a problem with recursion in the Rules action due to
   * autoSaveContext(). Hence better for Scheduler to provide this action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be unpublished.
   */
  public function doExecute(EntityInterface $entity) {
    $entity->setUnpublished();
  }

}
