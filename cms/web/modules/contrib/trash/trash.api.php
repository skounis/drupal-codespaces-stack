<?php

/**
 * @file
 * Hooks and documentation related to Trash.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act before entity soft-deletion.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that is about to be soft-deleted.
 *
 * @ingroup entity_crud
 * @see hook_ENTITY_TYPE_pre_trash_delete()
 */
function hook_entity_pre_trash_delete(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Act before entity soft-deletion of a particular entity type.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that is about to be soft-deleted.
 *
 * @ingroup entity_crud
 * @see hook_entity_pre_trash_delete()
 */
function hook_ENTITY_TYPE_pre_trash_delete(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Respond to entity soft-deletion.
 *
 * This hook runs once the entity has been soft-deleted from the storage.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that has been soft-deleted.
 *
 * @ingroup entity_crud
 * @see hook_ENTITY_TYPE_trash_delete()
 */
function hook_entity_trash_delete(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Respond to entity soft-deletion of a particular type.
 *
 * This hook runs once the entity has been soft-deleted from the storage.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that has been soft-deleted.
 *
 * @ingroup entity_crud
 * @see hook_entity_trash_delete()
 */
function hook_ENTITY_TYPE_trash_delete(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Act before restoring an entity from trash.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that is about to be restored.
 *
 * @ingroup entity_crud
 * @see hook_ENTITY_TYPE_pre_trash_restore()
 */
function hook_entity_pre_trash_restore(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Act before restoring an entity of a particular type from trash.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that is about to be restored.
 *
 * @ingroup entity_crud
 * @see hook_entity_pre_trash_restore()
 */
function hook_ENTITY_TYPE_pre_trash_restore(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Respond to restoring an entity from trash.
 *
 * This hook runs once the entity has been restored from trash.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that has been restored.
 *
 * @ingroup entity_crud
 * @see hook_ENTITY_TYPE_trash_restore()
 */
function hook_entity_trash_restore(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * Respond to restoring an entity of a particular type from trash.
 *
 * This hook runs once the entity has been restored from trash.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity object for the entity that has been restored.
 *
 * @ingroup entity_crud
 * @see hook_entity_trash_restore()
 */
function hook_ENTITY_TYPE_trash_restore(\Drupal\Core\Entity\EntityInterface $entity) {
}

/**
 * @} End of "addtogroup hooks".
 */
