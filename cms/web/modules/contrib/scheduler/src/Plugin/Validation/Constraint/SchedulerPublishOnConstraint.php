<?php

namespace Drupal\scheduler\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates publish on values.
 *
 * @Constraint(
 *   id = "SchedulerPublishOn",
 *   label = @Translation("Scheduler publish on", context = "Validation"),
 *   type = "entity"
 * )
 */
class SchedulerPublishOnConstraint extends CompositeConstraintBase {

  /**
   * Message shown when publish_on is not in the future.
   *
   * @var string
   */
  public $messagePublishOnDateNotInFuture = "The 'publish on' date must be in the future.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['publish_on'];
  }

}
