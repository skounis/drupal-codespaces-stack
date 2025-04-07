<?php

/**
 * @file
 * Install, update, and uninstall functions for the Trash module.
 */

/**
 * Update the enabled entity types and bundles configuration.
 */
function trash_post_update_set_enabled_entity_types_bundles(): void {
  // This was moved to trash_update_9301.
  // @see https://www.drupal.org/project/trash/issues/3453832
}

/**
 * Add missing 'auto_purge' configuration.
 */
function trash_post_update_fix_missing_auto_purge(): void {
  $config = \Drupal::configFactory()->getEditable('trash.settings');
  if ($config->get('auto_purge') === NULL) {
    $config->set('auto_purge', [
      'enabled' => FALSE,
      'after' => '30 days',
    ]);
    $config->save(TRUE);
  }
}

/**
 * Rebuild the container to register trash handlers.
 */
function trash_post_update_add_trash_handlers(): void {
  // Empty update to trigger a container rebuild.
}
