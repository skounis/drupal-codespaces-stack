<?php

/**
 * Create temporary table and store data from easy_email entities.
 */
function easy_email_post_update_00001(&$sandbox) {
  $connection = \Drupal::database();
  try {
    $connection->schema()->createTable('easy_email_temp', [
      'description' => 'Temporary table to store data from easy_email entities.',
      'fields' => [
        'id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'vid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'data' => [
          'type' => 'blob',
          'not null' => TRUE,
        ]
      ],
    ]);
  }
  catch (\Drupal\Core\Database\SchemaObjectExistsException $e) {
    // This is okay.
  }

  $easy_email_storage = \Drupal::entityTypeManager()->getStorage('easy_email');

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = $easy_email_storage->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $sandbox['current'] = 0;
  }
  // Loop through the easy_email entities 100 at a time.
  // Store the progress in the sandbox variable.
  if (!isset($sandbox['last_id'])) {
    $sandbox['last_id'] = 0;
  }

  $easy_email_ids = $easy_email_storage->getQuery()
    ->condition('id', $sandbox['last_id'], '>')
    ->accessCheck(FALSE)
    ->sort('id')
    ->range(0, 100)
    ->execute();
  $easy_emails = $easy_email_storage->loadMultiple($easy_email_ids);
  if (count($easy_emails) === 0) {
    $sandbox['#finished'] = 1;
    return;
  }
  else {
    $sandbox['#finished'] = 0;
  }
  foreach ($easy_emails as $easy_email) {
    // Get all the revisions of the entity.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $easy_email_storage */
    $revision_ids = $easy_email_storage->revisionIds($easy_email);
    // Loop through the revisions and store the data in the temporary table.
    foreach ($revision_ids as $revision_id) {
      $revision = $easy_email_storage->loadRevision($revision_id);
      if ($revision !== NULL) {
        $field_values = $revision->toArray();
        $field_values = serialize($field_values);
        $connection->insert('easy_email_temp')
          ->fields([
            'id' => $revision->id(),
            'vid' => $revision->getRevisionId(),
            'data' => $field_values,
          ])
          ->execute();
      }
    }
    $sandbox['last_id'] = $easy_email->id();
    $sandbox['current']++;
  }
  return $sandbox['current'] . ' emails of ' . $sandbox['total'] . ' stored in temp table.';
}

/**
 * Delete all easy_email entities.
 */
function easy_email_post_update_00002(&$sandbox) {
  $easy_email_storage = \Drupal::entityTypeManager()->getStorage('easy_email');

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = $easy_email_storage->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $sandbox['current'] = 0;
  }
  // Loop through the easy_email entities 100 at a time.
  $easy_email_ids = $easy_email_storage->getQuery()
    ->accessCheck(FALSE)
    ->sort('id')
    ->range(0, 100)
    ->execute();
  $easy_emails = $easy_email_storage->loadMultiple($easy_email_ids);
  if (count($easy_emails) === 0) {
    $sandbox['#finished'] = 1;
    return;
  }
  else {
    $sandbox['#finished'] = 0;
  }
  $easy_email_storage->delete($easy_emails);
  $sandbox['current'] += count($easy_emails);

  return $sandbox['current'] . ' emails of ' . $sandbox['total'] . ' deleted.';
}

/**
 * Remove all easy_email module bundle fields from the easy_email types.
 */
function easy_email_post_update_00003(&$sandbox) {
  $easy_email_bundle_fields = [
    'key',
    'recipient_uid',
    'cc_uid',
    'cc_address',
    'bcc_uid',
    'bcc_address',
    'from_name',
    'from_address',
    'reply_to',
    'body_html',
    'body_plain',
    'inbox_preview',
    'attachment',
    'attachment_path',
  ];
  $easy_email_type_storage = \Drupal::entityTypeManager()->getStorage('easy_email_type');

  $easy_email_type_ids = $easy_email_type_storage->getQuery()
    ->accessCheck(FALSE)
    ->execute();
  foreach ($easy_email_type_ids as $easy_email_type) {
    foreach ($easy_email_bundle_fields as $field_name) {
      $field_config = \Drupal\field\Entity\FieldConfig::loadByName('easy_email', $easy_email_type, $field_name);
      if ($field_config !== NULL) {
        $field_config->delete();
      }
    }
  }

  foreach ($easy_email_bundle_fields as $field_name) {
    $field_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('easy_email', $field_name);
    if ($field_storage !== NULL) {
      $field_storage->delete();
    }
  }
}

/**
 * Uninstall easy_email entity type
 */
function easy_email_post_update_00004(&$sandbox) {
  $entity_update_manager = \Drupal::entityDefinitionUpdateManager();
  $entity_type = $entity_update_manager->getEntityType('easy_email');
  $entity_update_manager->uninstallEntityType($entity_type);
}


/**
 * Re-install new easy_email entity type
 */
function easy_email_post_update_00005(&$sandbox) {
  $entity_type_definition = \Drupal::service('entity_type.manager')->getDefinition('easy_email');
  \Drupal::entityDefinitionUpdateManager()->installEntityType($entity_type_definition);
}

/**
 * Update the easy email types for the new settings.
 */
function easy_email_post_update_00006(&$sandbox) {
  $easy_email_type_storage = \Drupal::entityTypeManager()->getStorage('easy_email_type');
  $easy_email_type_ids = $easy_email_type_storage->getQuery()
    ->accessCheck(FALSE)
    ->execute();
  foreach ($easy_email_type_ids as $easy_email_type_id) {
    $easy_email_type = $easy_email_type_storage->load($easy_email_type_id);
    if ($easy_email_type !== NULL) {
      $easy_email_type->set('save_email', TRUE);
      $easy_email_type->set('allow_saving_email', TRUE);
      $easy_email_type->set('purge_emails', FALSE);
      $easy_email_type->set('purge_interval', NULL);
      $easy_email_type->set('purge_period', 'days');
      $easy_email_type->save();
    }
  }
}

/**
 * Copy data from temporary table to new easy_email entity type
 */
function easy_email_post_update_00007(&$sandbox) {
  $connection = \Drupal::database();
  $easy_email_storage = \Drupal::entityTypeManager()->getStorage('easy_email');

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = $connection->select('easy_email_temp', 'eet')
      ->fields('eet', ['id'])
      ->countQuery()
      ->execute()
      ->fetchField();
    $sandbox['current'] = 0;
  }
  // Loop through the easy_email entities 100 at a time.
  // Store the progress in the sandbox variable.
  if (!isset($sandbox['last_id'])) {
    $sandbox['last_id'] = 0;
  }

  $easy_email_data = $connection->select('easy_email_temp', 'eet')
    ->fields('eet')
    ->condition('id', $sandbox['last_id'], '>')
    ->orderBy('id')
    ->range(0, 100)
    ->execute()->fetchAll();
  if (count($easy_email_data) === 0) {
    $sandbox['#finished'] = 1;
    return;
  }
  else {
    $sandbox['#finished'] = 0;
  }
  $flatten_fields = [
    'id' => 'value',
    'vid' => 'value',
    'uuid' => 'value',
    'type' => 'target_id',
    'revision_created' => 'value',
    'revision_user' => 'target_id',
    'revision_log_message' => 'value',
    'revision_default' => 'value',
  ];
  $skip_fields = [
    'langcode',
    'revision_translation_affected',
  ];
  foreach ($easy_email_data as $easy_email_datum) {
    $field_values = (array) unserialize($easy_email_datum->data);
    $values = [];
    foreach ($field_values as $field_name => $field_value) {
      if (in_array($field_name, $skip_fields, TRUE)) {
        continue;
      }
      if (array_key_exists($field_name, $flatten_fields)) {
        $field_value = !empty($field_value[0][$flatten_fields[$field_name]]) ? $field_value[0][$flatten_fields[$field_name]] : NULL;
      }
      $values[$field_name] = $field_value;
    }
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $easy_email */
    $easy_email = $easy_email_storage->create($values);
    $easy_email->save();
    $sandbox['last_id'] = $easy_email->id();
    $sandbox['current']++;
  }
  return $sandbox['current'] . ' emails of ' . $sandbox['total'] . ' migrated to new easy_email entity type.';
}

/**
 * Delete the temporary table.
 */
function easy_email_post_update_00008(&$sandbox) {
  $connection = \Drupal::database();
  $connection->schema()->dropTable('easy_email_temp');
}

/**
 * Set default global settings
 */
function easy_email_post_update_00009(&$sandbox) {
  $config = \Drupal::configFactory()->getEditable('easy_email.settings');
  $config->set('purge_on_cron', TRUE);
  $config->set('purge_cron_limit', 50);
  $config->set('allowed_attachment_paths', ['public://*']);
  $config->save();
}
