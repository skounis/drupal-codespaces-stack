<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Base class for Scheduler Content Moderation Integration validators.
 *
 * @package Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint
 */
abstract class ConstraintValidatorBase extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * SchedulerModerationConstraintValidator constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The content moderation information service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(
    protected ModerationInformationInterface $moderationInformation,
    protected AccountInterface $account,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_moderation.moderation_information'),
      $container->get('current_user')
    );
  }

  /**
   * Gets the workflow type from the supplied entity's configured workflow.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to get the workflow type for.
   *
   * @return \Drupal\workflows\WorkflowTypeInterface
   *   The workflow type.
   */
  protected function getEntityWorkflowType(ContentEntityInterface $entity) {
    return $this->moderationInformation
      ->getWorkflowForEntity($entity)
      ->getTypePlugin();
  }

  /**
   * Validate transition.
   *
   * Validate that the transition between the supplied states is a valid
   * transition for the supplied entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity containing the workflow to check against.
   * @param string $from_state
   *   The state to transition from.
   * @param string $to_state
   *   The state to transition to.
   *
   * @return bool
   *   TRUE if it's a valid transition. FALSE, otherwise.
   */
  protected function isValidTransition(ContentEntityInterface $entity, $from_state, $to_state) {
    $workflow_type = $this->getEntityWorkflowType($entity);

    if (!$workflow_type->hasState($from_state) || !$workflow_type->hasState($to_state)) {
      return FALSE;
    }

    $from = $workflow_type->getState($from_state);
    return $from->canTransitionTo($to_state);
  }

}
