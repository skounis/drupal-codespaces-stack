<?php

/**
 * @file
 * Test fixture to set up enabled modules and configuration.
 *
 * The update test was added when the state fields were hidden by default.
 * @see https://www.drupal.org/i/3317999
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

// Enable the four additional modules.
$ext = $connection->select('config')
  ->fields('config', ['data'])
  ->where("name = 'core.extension'")
  ->execute();
// phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize
$ext = unserialize($ext->fetchObject()->data);
$ext['module']['mysql'] = 0;
$ext['module']['workflows'] = 0;
$ext['module']['content_moderation'] = 0;
$ext['module']['scheduler'] = 0;
$ext['module']['scheduler_content_moderation_integration'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($ext),
  ])
  ->where("name = 'core.extension'")
  ->execute();

// Set the schema numbers for the new modules.
$schemas = [
  'mysql' => 's:4:"9000";',
  'workflows' => 's:4:"9000";',
  'content_moderation' => 's:4:"9000";',
  'scheduler' => 's:4:"8208";',
  'scheduler_content_moderation_integration' => 's:4:"9000";',
];
foreach ($schemas as $name => $value) {
  $connection->upsert('key_value')
    ->key('name')
    ->fields(['collection', 'name', 'value'])
    ->values(['system.schema', $name, $value])
    ->execute();
}

// Add entity config files for article and workflow. These were exported from a
// clean site after the article entity type was enabled for scheduled publishing
// but not unpublishing, and added to the default editorial workflow.
$configs = ['node.type.article', 'workflows.workflow.editorial'];
foreach ($configs as $name) {
  $connection->update('config')
    ->fields([
      'data' => serialize(Yaml::decode(file_get_contents(__DIR__ . "/{$name}.yml"))),
    ])
    ->where("name = '{$name}'")
    ->execute();
}

// Add the missing post_update 'existing_updates' to the array, so that the
// first update.php page does not fail with missing updates error.
$existing_updates = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
// phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize
$existing_updates = unserialize($existing_updates);
$existing_updates = array_merge($existing_updates, [
  'language_post_update_language_select_widget',
  'locale_post_update_clear_cache_for_old_translations',
  'responsive_image_post_update_recreate_dependencies',
  'rest_post_update_create_rest_resource_config_entities',
  'rest_post_update_resource_granularity',
  'rest_post_update_161923',
  'action_post_update_move_plugins',
  'action_post_update_remove_settings',
  'migrate_post_update_clear_migrate_source_count_cache',
  'migrate_drupal_post_update_uninstall_multilingual',
  'rest_post_update_delete_settings',
  'serialization_post_update_delete_settings',
]);
$connection->update('key_value')
  ->fields([
    'value' => serialize($existing_updates),
  ])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute();
