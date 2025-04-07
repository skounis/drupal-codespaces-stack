<?php

/**
 * @file
 * Contains SCMI implementations of Scheduler hook functions.
 */

/**
 * Implements hook_scheduler_hide_publish_date().
 *
 * This hook is called from scheduler_form_{type}_form_alter() and returns TRUE
 * if the scheduler publish_on field should be hidden.
 */
function scheduler_content_moderation_integration_scheduler_hide_publish_date($form, $form_state, $entity): bool {
  /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_information */
  $moderation_information = \Drupal::service('content_moderation.moderation_information');

  $return = FALSE;
  if ($moderation_information->isModeratedEntity($entity)) {
    $fieldStorageDefinition = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())['publish_state']
      ->getFieldStorageDefinition();
    $options = _scheduler_content_moderation_integration_states_values($fieldStorageDefinition, $entity);
    // If no moderation transitions are available for publish_state then hide
    // the publish_on scheduler field.
    $options_without_none = array_diff_key($options, ['_none' => '']);
    $return = (count($options_without_none) == 0);
  }
  return $return;
}

/**
 * Implements hook_scheduler_hide_unpublish_date().
 *
 * This hook is called from scheduler_form_{type}_form_alter() and returns TRUE
 * if the scheduler unpublish_on field should be hidden.
 */
function scheduler_content_moderation_integration_scheduler_hide_unpublish_date($form, $form_state, $entity): bool {
  /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_information */
  $moderation_information = \Drupal::service('content_moderation.moderation_information');

  $return = FALSE;
  if ($moderation_information->isModeratedEntity($entity)) {
    // If no moderation transitions are available for unpublish_state then hide
    // the unpublish_on scheduler field.
    $fieldStorageDefinition = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())['unpublish_state']
      ->getFieldStorageDefinition();
    $options = _scheduler_content_moderation_integration_states_values($fieldStorageDefinition, $entity);
    $options_without_none = array_diff_key($options, ['_none' => '']);
    $return = (count($options_without_none) == 0);
  }
  return $return;
}

/**
 * Implements hook_scheduler_publish_process().
 *
 * This hook is called from schedulerManger::publish(). The return values are:
 * 1  if the entity has been processed here and hence should not be published
 *    via Scheduler.
 * -1 if an exception is thrown, to abandon processing this entity in Scheduler.
 * 0  if not moderated, to let Scheduler process the entity as normal.
 */
function scheduler_content_moderation_integration_scheduler_publish_process($entity): int {
  /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_information */
  $moderation_information = \Drupal::service('content_moderation.moderation_information');

  if (!$moderation_information->isModeratedEntity($entity)) {
    return 0;
  }

  $state = $entity->publish_state->value;
  $entity->publish_state->value = NULL;

  /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
  $type_plugin = $moderation_information->getWorkflowForEntity($entity)->getTypePlugin();
  try {
    $type_plugin->getTransitionFromStateToState($entity->moderation_state->value, $state);
    $entity->set('moderation_state', $state)->save();
    return 1;
  }
  catch (\InvalidArgumentException) {
    // If transition is not valid, throw exception.
    return -1;
  }
}

/**
 * Implements hook_scheduler_unpublish_process().
 *
 * This hook is called from schedulerManger::unpublish(). The return values are:
 * 1  if the entity has been processed here and hence should not be unpublished
 *    via Scheduler.
 * -1 if an exception is thrown, to abandon processing this entity in Scheduler.
 * 0  if not moderated, to let Scheduler process the entity as normal.
 */
function scheduler_content_moderation_integration_scheduler_unpublish_process($entity): int {
  /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_information */
  $moderation_information = \Drupal::service('content_moderation.moderation_information');

  if (!$moderation_information->isModeratedEntity($entity)) {
    return 0;
  }

  $state = $entity->unpublish_state->value;
  $entity->unpublish_state->value = NULL;

  /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
  $type_plugin = $moderation_information->getWorkflowForEntity($entity)->getTypePlugin();
  try {
    $type_plugin->getTransitionFromStateToState($entity->moderation_state->value, $state);
    $entity->set('moderation_state', $state)->save();
    return 1;
  }
  catch (\InvalidArgumentException) {
    // If transition is not valid, throw exception.
    return -1;
  }
}
