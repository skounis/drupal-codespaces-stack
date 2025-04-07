<?php

namespace Drupal\eca_migrate\Plugin\ECA\Event;

use Drupal\eca\Attributes\Token;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;

/**
 * Plugin implementation of the ECA Events for migrate.
 *
 * @EcaEvent(
 *   id = "migrate",
 *   deriver = "Drupal\eca_migrate\Plugin\ECA\Event\MigrateEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class MigrateEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    $actions['idmap_message'] = [
      'label' => 'Save message to ID map',
      'event_name' => MigrateEvents::IDMAP_MESSAGE,
      'event_class' => MigrateIdMapMessageEvent::class,
    ];
    $actions['map_delete'] = [
      'label' => 'Remove entry from migration map',
      'event_name' => MigrateEvents::MAP_DELETE,
      'event_class' => MigrateMapDeleteEvent::class,
    ];
    $actions['map_save'] = [
      'label' => 'Save to migration map',
      'event_name' => MigrateEvents::MAP_SAVE,
      'event_class' => MigrateMapSaveEvent::class,
    ];
    $actions['post_import'] = [
      'label' => 'Migration import finished',
      'event_name' => MigrateEvents::POST_IMPORT,
      'event_class' => MigrateImportEvent::class,
    ];
    $actions['post_rollback'] = [
      'label' => 'Migration rollback finished',
      'event_name' => MigrateEvents::POST_ROLLBACK,
      'event_class' => MigrateRollbackEvent::class,
    ];
    $actions['post_row_delete'] = [
      'label' => 'Migration row deleted',
      'event_name' => MigrateEvents::POST_ROW_DELETE,
      'event_class' => MigrateRowDeleteEvent::class,
    ];
    $actions['post_row_save'] = [
      'label' => 'Migration row saved',
      'event_name' => MigrateEvents::POST_ROW_SAVE,
      'event_class' => MigratePostRowSaveEvent::class,
    ];
    $actions['pre_import'] = [
      'label' => 'Migration import started',
      'event_name' => MigrateEvents::PRE_IMPORT,
      'event_class' => MigrateImportEvent::class,
    ];
    $actions['pre_rollback'] = [
      'label' => 'Migration rollback started',
      'event_name' => MigrateEvents::PRE_ROLLBACK,
      'event_class' => MigrateRollbackEvent::class,
    ];
    $actions['pre_row_delete'] = [
      'label' => 'Deleting migration row',
      'event_name' => MigrateEvents::PRE_ROW_DELETE,
      'event_class' => MigrateRowDeleteEvent::class,
    ];
    $actions['pre_row_save'] = [
      'label' => 'Saving migration row',
      'event_name' => MigrateEvents::PRE_ROW_SAVE,
      'event_class' => MigratePreRowSaveEvent::class,
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'migration',
    description: 'The migration plugin being run.',
    classes: [
      MigrateImportEvent::class,
      MigratePreRowSaveEvent::class,
      MigrateRollbackEvent::class,
      MigrateRowDeleteEvent::class,
      MigrateIdMapMessageEvent::class,
    ]
  )]
  #[Token(
    name: 'map',
    description: 'The map plugin that caused the event to fire.',
    classes: [
      MigrateMapSaveEvent::class,
      MigrateMapDeleteEvent::class,
    ]
  )]
  #[Token(
    name: 'fields',
    description: 'Array of map fields, keyed by field name.',
    classes: [
      MigrateMapSaveEvent::class,
    ]
  )]
  #[Token(
    name: 'source_id',
    description: 'The source ID values.',
    classes: [
      MigrateMapDeleteEvent::class,
    ]
  )]
  #[Token(
    name: 'row',
    description: 'The row about to be imported.',
    classes: [
      MigratePreRowSaveEvent::class,
      MigrateRowDeleteEvent::class,
    ]
  )]
  #[Token(
    name: 'destination_id_values',
    description: 'The row\'s destination ID.',
    classes: [
      MigratePostRowSaveEvent::class,
      MigrateRowDeleteEvent::class,
    ]
  )]
  #[Token(
    name: 'source_id_values',
    description: 'The source ID values.',
    classes: [
      MigrateIdMapMessageEvent::class,
    ]
  )]
  #[Token(
    name: 'message',
    description: 'The message to be logged.',
    classes: [
      MigrateIdMapMessageEvent::class,
    ]
  )]
  #[Token(
    name: 'level',
    description: 'The severity level of the message.',
    classes: [
      MigrateIdMapMessageEvent::class,
    ]
  )]
  public function getData(string $key): mixed {
    $event = $this->event;

    switch ($key) {
      case 'migration':
        if ($event instanceof MigrateImportEvent
          || $event instanceof MigratePreRowSaveEvent
          || $event instanceof MigrateRollbackEvent
          || $event instanceof MigrateRowDeleteEvent
          || $event instanceof MigrateIdMapMessageEvent
        ) {
          return $event->getMigration();
        }
        break;

      case 'map':
        if ($event instanceof MigrateMapSaveEvent
          || $event instanceof MigrateMapDeleteEvent
        ) {
          return $event->getMap();
        }
        break;

      case 'fields':
        if ($event instanceof MigrateMapSaveEvent) {
          return $event->getFields();
        }
        break;

      case 'source_id':
        if ($event instanceof MigrateMapDeleteEvent) {
          return $event->getSourceId();
        }
        break;

      case 'row':
        if ($event instanceof MigratePreRowSaveEvent
          || $event instanceof MigrateRowDeleteEvent
        ) {
          return $event->getRow();
        }
        break;

      case 'destination_id_values':
        if ($event instanceof MigratePostRowSaveEvent
          || $event instanceof MigrateRowDeleteEvent
        ) {
          return $event->getDestinationIdValues();
        }
        break;

      case 'source_id_values':
        if ($event instanceof MigrateIdMapMessageEvent) {
          return $event->getSourceIdValues();
        }
        break;

      case 'message':
        if ($event instanceof MigrateIdMapMessageEvent) {
          return $event->getMessage();
        }
        break;

      case 'level':
        if ($event instanceof MigrateIdMapMessageEvent) {
          return $event->getLevel();
        }
        break;

    }

    return parent::getData($key);
  }

}
