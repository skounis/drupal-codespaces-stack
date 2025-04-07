<?php

namespace Drupal\focal_point\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Changes the widget for image and media field according to D7 widget settings.
 */
class MigrationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    if (!class_exists('Drupal\migrate\Event\MigrateEvents')) {
      return [];
    }

    return [
      MigrateEvents::PRE_ROW_SAVE => ['onPreRowSave'],
    ];
  }

  /**
   * Changes field widget to focal point for image media entities when needed.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Prepare row event.
   */
  public function onPreRowSave(MigratePreRowSaveEvent $event) {
    $this->onFieldInstanceWidgetPreRowSave($event);
    $this->onMediaMigrationWidgetPreRowSave($event);
  }

  /**
   * Changes field widget settings for media.
   *
   * This is an optional support for Media Migration module.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Prepare row event.
   */
  protected function onMediaMigrationWidgetPreRowSave(MigratePreRowSaveEvent $event) {
    $base_id = $event->getMigration()->getBaseId();
    if (!in_array($base_id, ['d7_file_entity_widget', 'd7_file_plain_widget'], TRUE)) {
      return;
    }
    $focal_point_enabled_for = $this->getFocalPointEnabledFor($event);
    $source_field_widget = $event->getRow()->getDestinationProperty('options/type');
    if (in_array('media', $focal_point_enabled_for) && $source_field_widget === 'image_image') {
      $event->getRow()->setDestinationProperty('options/type', 'image_focal_point');
    }
  }

  /**
   * Change widget settings for image.
   */
  protected function onFieldInstanceWidgetPreRowSave(MigratePreRowSaveEvent $event) {
    if (!preg_match('/d7_field_instance_widget_settings/', $event->getMigration()->id())) {
      return;
    }
    if ($event->getRow()->getSourceProperty('type') !== 'image') {
      return;
    }
    $focal_point_enabled_for = $this->getFocalPointEnabledFor($event);
    if (in_array('image', $focal_point_enabled_for)) {
      $event->getRow()->setDestinationProperty('options/type', 'image_focal_point');
    }
  }

  /**
   * Helper function to fetch focal point enabled for.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Prepare row event.
   *
   * @return array
   *   Array for which focal point is enabled for.
   */
  private function getFocalPointEnabledFor(MigratePreRowSaveEvent $event): array {
    $migration_source = $event->getMigration()->getSourcePlugin();
    if (!$migration_source instanceof DrupalSqlBase) {
      return [];
    }
    $result = $event->getMigration()->getSourcePlugin()->getDatabase()->select('variable', 'v')
      ->fields('v', ['value'])
      ->condition('name', 'focal_point_enabled_for')
      ->execute()
      ->fetchField();
    return $result !== FALSE ? unserialize($result) : ['media', 'image'];
  }

}
