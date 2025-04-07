<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates content moderation transition access.
 *
 * @Constraint(
 *   id = "SchedulerModerationTransitionAccess",
 *   label = @Translation("Scheduler content moderation transition access validation", context = "Validation"),
 *   type = "string"
 * )
 */
class TransitionAccessConstraint extends Constraint {

  /**
   * No access message.
   *
   * @var string
   */
  public string $noAccessMessage = 'You do not have access to transition from %original_state to %new_state';

}
