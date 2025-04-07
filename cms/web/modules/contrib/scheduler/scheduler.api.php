<?php

/**
 * @file
 * API documentation for the Scheduler module.
 *
 * Each of these hook functions has a general version which is invoked for all
 * entity types, and a specific variant with _{type}_ in the name, invoked when
 * processing that specific entity type.
 *
 * phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis.UndefinedVariable
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Hook function to add entity ids to the list being processed.
 *
 * This hook allows modules to add more entity ids into the list being processed
 * in the current cron run. It is invoked during cron runs only. This function
 * is retained for backwards compatibility but is superseded by the more
 * flexible hook_scheduler_list_alter().
 *
 * @param string $process
 *   The process being done - 'publish' or 'unpublish'.
 * @param string $entityTypeId
 *   The type of the entity being processed, for example 'node' or 'media'.
 *
 * @return array
 *   Array of ids to add to the existing list to be processed. Duplicates are
 *   removed when all hooks have been invoked.
 */
function hook_scheduler_list($process, $entityTypeId) {
  $ids = [];
  // Do some processing to add ids to the $ids array.
  return $ids;
}

/**
 * Entity-type specific version of hook_scheduler_list().
 *
 * The parameters and return value match the general variant of this hook. The
 * $entityTypeId parameter is included for ease and consistency, but is not
 * strictly necessary as it will always match the TYPE in the function name.
 */
function hook_scheduler_TYPE_list($process, $entityTypeId) {
}

/**
 * Hook function to manipulate the list of entity ids being processed.
 *
 * This hook allows modules to add or remove entity ids from the list being
 * processed in the current cron run. It is invoked during cron runs only.
 *
 * @param array $ids
 *   The array of entity ids being processed.
 * @param string $process
 *   The process being done - 'publish' or 'unpublish'.
 * @param string $entityTypeId
 *   The type of the entity being processed, for example 'node' or 'media'.
 */
function hook_scheduler_list_alter(array &$ids, $process, $entityTypeId) {
  if ($process == 'publish' && $some_condition) {
    // Set a publish_on date and add the id.
    $entity->set('publish_on', \Drupal::time()->getRequestTime())->save();
    $ids[] = $id;
  }
  if ($process == 'unpublish' && $some_other_condition) {
    // Remove the id.
    $ids = array_diff($ids, [$id]);
  }
  // No return is necessary because $ids is passed by reference. Duplicates are
  // removed when all hooks have been invoked.
}

/**
 * Entity-type specific version of hook_scheduler_list_alter().
 *
 * The parameters match the general variant of this hook.
 */
function hook_scheduler_TYPE_list_alter(array &$ids, $process, $entityTypeId) {
}

/**
 * Hook function to deny publishing of an entity.
 *
 * This hook gives modules the ability to prevent publication of an entity. The
 * entity may be scheduled, and an attempt to publish it will be made during the
 * first cron run after the publishing time. If any implementation of this hook
 * function returns FALSE the entity will not be published. Attempts to publish
 * will continue on each subsequent cron run, and the entity will be published
 * when no hook prevents it.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The scheduled entity that is about to be published.
 *
 * @return bool|null
 *   FALSE if the entity should not be published. TRUE or NULL will not affect
 *   the outcome.
 */
function hook_scheduler_publishing_allowed(EntityInterface $entity) {
  // Do some logic here ...
  $allowed = !empty($entity->field_approved->value);
  // If publication is denied then inform the user why. This message will be
  // displayed during entity edit and save.
  if (!$allowed) {
    \Drupal::messenger()->addMessage(t('The content will only be published after approval.'), 'status', FALSE);
    // If the time is in the past it means that the action has been prevented,
    // so write a dblog message to show this.
    if ($entity->publish_on->value <= \Drupal::time()->getRequestTime()) {
      if ($entity->id() && $entity->hasLinkTemplate('canonical')) {
        $link = $entity->toLink(t('View'))->toString();
      }
      \Drupal::logger('scheduler_api_test')->warning('Publishing of "%title" is prevented until approved.', [
        '%title' => $entity->label(),
        'link' => $link ?? NULL,
      ]);
    }
  }
  return $allowed;
}

/**
 * Entity-type specific version of hook_scheduler_publishing_allowed().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_publishing_allowed(EntityInterface $entity) {
}

/**
 * Hook function to deny unpublishing of an entity.
 *
 * This hook gives modules the ability to prevent unpublication of an entity.
 * The entity may be scheduled, and an attempt to unpublish it will be made
 * during the first cron run after the unpublishing time. If any implementation
 * of this hook function returns FALSE the entity will not be unpublished.
 * Attempts to unpublish will continue on each subsequent cron run, and the
 * entity will be unpublished when no hook prevents it.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The scheduled entity that is about to be unpublished.
 *
 * @return bool|null
 *   FALSE if the entity should not be unpublished. TRUE or NULL will not affect
 *   the outcome.
 */
function hook_scheduler_unpublishing_allowed(EntityInterface $entity) {
  $allowed = TRUE;
  // Prevent unpublication of competitions if not all prizes have been claimed.
  if ($entity->getEntityTypeId() == 'competition' && $items = $entity->field_competition_prizes->getValue()) {
    $allowed = (bool) count($items);

    // If unpublication is denied then inform the user why. This message will be
    // displayed during entity edit and save.
    if (!$allowed) {
      \Drupal::messenger()->addMessage(t('The competition will only be unpublished after all prizes have been claimed.'));
    }
  }
  return $allowed;
}

/**
 * Entity-type specific version of hook_scheduler_unpublishing_allowed().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_unpublishing_allowed(EntityInterface $entity) {
}

/**
 * Hook function to hide the Publish On field.
 *
 * This hook is called from scheduler_form_alter() when adding or editing an
 * entity. It gives modules the ability to hide the scheduler publish_on input
 * field so that a date may not be entered or changed. Note that it does not
 * give the ability to force the field to be displayed, as that could override a
 * more significant setting. It can only be used to hide the field.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 * See https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @param array $form
 *   An associative array containing the structure of the form, as used in
 *   hook_form_alter().
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form, as used in hook_form_alter().
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being added or edited.
 *
 * @return bool
 *   TRUE to hide the publish_on field.
 *   FALSE or NULL to leave the setting unchanged.
 */
function hook_scheduler_hide_publish_date(array $form, FormStateInterface $form_state, EntityInterface $entity) {
  if ($some_condition) {
    return TRUE;
  }
}

/**
 * Entity-type specific version of hook_scheduler_hide_publish_date().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_hide_publish_date(array $form, FormStateInterface $form_state, EntityInterface $entity) {
}

/**
 * Hook function to hide the Unpublish On field.
 *
 * This hook is called from scheduler_form_alter() when adding or editing an
 * entity. It gives modules the ability to hide the scheduler unpublish_on input
 * field so that a date may not be entered or changed. Note that it does not
 * give the ability to force the field to be displayed, as that could override a
 * more significant setting. It can only be used to hide the field.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 * See https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @param array $form
 *   An associative array containing the structure of the form, as used in
 *   hook_form_alter().
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form, as used in hook_form_alter().
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object being added or edited.
 *
 * @return bool
 *   TRUE to hide the unpublish_on field.
 *   FALSE or NULL to leave the setting unchanged.
 */
function hook_scheduler_hide_unpublish_date(array $form, FormStateInterface $form_state, EntityInterface $entity) {
  if ($some_condition) {
    return TRUE;
  }
}

/**
 * Entity-type specific version of hook_scheduler_hide_unpublish_date().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_hide_unpublish_date(array $form, FormStateInterface $form_state, EntityInterface $entity) {
}

/**
 * Hook function to process the publish action for an entity.
 *
 * This hook is called from schedulerManger::publish() and allows other modules
 * to process the publish action on the entity during a cron run. That module
 * may require different functionality to be executed instead of the default
 * publish action. If all of the invoked hook functions return 0 then Scheduler
 * will process the entity using the default publish action, just as if no hook
 * functions had been called.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 * See https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The scheduled entity that is about to be published.
 *
 * @return int
 *   1 if this function has published the entity or performed other such action
 *     meaning that Scheduler should NOT process the default publish action.
 *   0 if nothing has been done and Scheduler should process the default publish
 *     action just as if this hook function did not exist.
 *   -1 if an error has occurred and Scheduler should abandon processing this
 *     entity with no further action and move on to the next one.
 */
function hook_scheduler_publish_process(EntityInterface $entity) {
  if ($big_problem) {
    // Throw an exception here.
    return -1;
  }
  if ($some_condition) {
    // Do the publish processing here on the $entity.
    $entity->setSomeValue();
    return 1;
  }
  return 0;
}

/**
 * Entity-type specific version of hook_scheduler_publish_process().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_publish_process(EntityInterface $entity) {
}

/**
 * Hook function to process the unpublish action for an entity.
 *
 * This hook is called from schedulerManger::unpublish() and allows other
 * modules to process the unpublish action on the entity during a cron run. That
 * module may require different functionality to be executed instead of the
 * default unpublish action. If all of the invoked hook functions return 0 then
 * Scheduler will process the entity using the default unpublish action, just as
 * if no hook functions had been called.
 *
 * This hook was introduced for scheduler_content_moderation_integration.
 * See https://www.drupal.org/project/scheduler/issues/2798689
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The scheduled entity that is about to be unpublished.
 *
 * @return int
 *   1 if this function has unpublished the entity or performed other actions
 *     meaning that Scheduler should NOT process the default unpublish action.
 *   0 if nothing has been done and Scheduler should process the default
 *     unpublish action just as if this hook function did not exist.
 *   -1 if an error has occurred and Scheduler should abandon processing this
 *     entity with no further action and move on to the next one.
 */
function hook_scheduler_unpublish_process(EntityInterface $entity) {
  if ($big_problem) {
    // Throw an exception here.
    return -1;
  }
  if ($some_condition) {
    // Do the unpublish processing here on the $entity.
    $entity->setSomeValue();
    return 1;
  }
  return 0;
}

/**
 * Entity-type specific version of hook_scheduler_unpublish_process().
 *
 * The parameters and return match the general variant of this hook.
 */
function hook_scheduler_TYPE_unpublish_process(EntityInterface $entity) {
}

/*
In addition to the hook functions for manipulating the list of ids to process,
three query tags are added to the two database select queries, to enable other
modules to implement hook_query_TAG_alter(). These are not strictly Scheduler
hook functions, but are included here for completeness.
For examples of use see tests/modules/scheduler_extras/scheduler_extras.module.
 */

/**
 * Implements hook_query_TAG_alter() for TAG = scheduler.
 *
 * This is the top-level hook function to modify both the publish and unpublish
 * queries. The fields used in these conditions must be common to all entity
 * types that are enabled for scheduling on your site.
 */
function hook_query_scheduler_alter($query) {
  $entityTypeId = $query->getMetaData('entity_type');
  // Prevent all processing if either of the dates are more than 12 months ago.
  $query->condition($query->orConditionGroup()
    ->condition("{$entityTypeId}_field_revision.publish_on", strtotime('- 12 months'), '>')
    ->condition("{$entityTypeId}_field_revision.unpublish_on", strtotime('- 12 months'), '>')
  );
}

/**
 * Implements hook_query_TAG_alter() for TAG = scheduler_publish.
 *
 * This hook is executed when selecting which entities to publish. It is
 * applicable to all entity types.
 */
function hook_query_scheduler_publish_alter($query) {
}

/**
 * Implements hook_query_TAG_alter() for TAG = scheduler_TYPE_publish.
 *
 * This hook is executed when selecting entities of a specific type.
 */
function hook_query_scheduler_TYPE_publish_alter($query) {
}

/**
 * Implements hook_query_TAG_alter() for TAG = scheduler_unpublish.
 *
 * This hook is executed when selecting which entities to unpublish. It is
 * applicable to all entity types.
 */
function hook_query_scheduler_unpublish_alter($query) {
}

/**
 * Implements hook_query_TAG_alter() for TAG = scheduler_TYPE_unpublish.
 *
 * This hook is executed when selecting entities of a specific type.
 */
function hook_query_scheduler_TYPE_unpublish_alter($query) {
}

/**
 * @} End of "addtogroup hooks".
 */
