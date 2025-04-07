<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validator for the TransitionAccessConstraint.
 */
class TransitionAccessConstraintValidator extends ConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    /** @var \Drupal\Core\Field\FieldItemList $value */
    assert($constraint instanceof TransitionAccessConstraint);
    $entity = $value->getEntity();

    // No need to validate entities that are not moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return;
    }

    // No need to validate if a moderation state has not been set.
    if ($value->isEmpty()) {
      return;
    }

    $field_name = $value->getName();

    // No need to validate when there is no time set.
    if (
      ($field_name === 'publish_state' && !isset($entity->publish_on->value)) ||
      ($field_name === 'unpublish_state' && !isset($entity->unpublish_on->value))
    ) {
      return;
    }

    $from_state = $entity->moderation_state->value;
    $to_state = $value->value;

    // No need to validate if transition does not exist.
    if (!$this->isValidTransition($entity, $from_state, $to_state)) {
      return;
    }

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $workflow_type = $workflow->getTypePlugin();
    $from = $workflow_type->getState($from_state);
    $to = $workflow_type->getState($to_state);
    $transition = $from->getTransitionTo($to_state);

    if (!$this->account->hasPermission('use ' . $workflow->id() . ' transition ' . $transition->id())) {
      $this->context
        ->buildViolation($constraint->noAccessMessage, [
          '%original_state' => $from->label(),
          '%new_state' => $to->label(),
        ])
        ->atPath($field_name)
        ->addViolation();
    }
  }

}
