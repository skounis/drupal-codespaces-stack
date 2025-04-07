<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validator for the UnPublishStateConstraint.
 */
class UnPublishStateConstraintValidator extends ConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $value->getEntity();

    // No need to validate entities that are not moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return;
    }

    // No need to validate if a moderation state has not been set.
    if ($value->isEmpty()) {
      return;
    }
    // No need to validate when there is no time set.
    if (!isset($entity->unpublish_on->value)) {
      return;
    }

    // If there is no publish_on date then any publish_state value should be
    // ignored and the validation will run as if no publish_state was selected.
    $publish_state = ($entity->publish_state->value === '_none' || empty($entity->publish_on->value)) ? NULL : $entity->publish_state->value;
    $unpublish_state = $entity->unpublish_state->value;
    $moderation_state = $entity->moderation_state->value;

    // If the publish state has been set then we need to validate that the
    // transition from the set published state to the un-publish state is
    // a valid transition.
    if ($publish_state && !$this->isValidTransition($entity, $publish_state, $unpublish_state)) {
      $workflow_type = $this->getEntityWorkflowType($entity);
      $mod_state_label = $workflow_type->hasState($moderation_state) ? $workflow_type->getState($moderation_state)->label() : 'None';
      $unpub_state_label = $workflow_type->hasState($unpublish_state) ? $workflow_type->getState($unpublish_state)->label() : 'None';
      $pub_state_label = $workflow_type->hasState($publish_state) ? $workflow_type->getState($publish_state)->label() : 'None';
      $this->context
        ->buildViolation($constraint->invalidPublishToUnPublishTransitionMessage, [
          '%publish_state' => $pub_state_label,
          '%unpublish_state' => $unpub_state_label,
        ])
        ->atPath('publish_state')
        ->addViolation();
    }

    // If a publishing state has not been set then we need to validate that
    // the un-publish state is a valid transition based on the entity's
    // current moderation state.
    if (!$publish_state && !$this->isValidTransition($entity, $moderation_state, $unpublish_state)) {
      $workflow_type = $this->getEntityWorkflowType($entity);
      $mod_state_label = $workflow_type->hasState($moderation_state) ? $workflow_type->getState($moderation_state)->label() : 'None';
      $unpub_state_label = $workflow_type->hasState($unpublish_state) ? $workflow_type->getState($unpublish_state)->label() : 'None';
      $pub_state_label = $workflow_type->hasState($publish_state) ? $workflow_type->getState($publish_state)->label() : 'None';
      $this->context
        ->buildViolation($constraint->invalidUnPublishTransitionMessage, [
          '%unpublish_state' => $unpub_state_label,
          '%content_state' => $mod_state_label,
        ])
        ->atPath('publish_state')
        ->addViolation();
    }
  }

}
