<?php

namespace Drupal\dashboard;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Defines a class for storing dashboard config entities.
 *
 * We need to map LB sections <=> arrays.
 */
class DashboardStorageHandler extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function mapToStorageRecord(EntityInterface $entity) {
    $record = parent::mapToStorageRecord($entity);

    if (!empty($record['layout'])) {
      $record['layout'] = array_map(function (Section $section) {
        return $section->toArray();
      }, $record['layout']);
    }
    return $record;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records) {
    foreach ($records as &$record) {
      if (!empty($record['layout'])) {
        $sections = &$record['layout'];
        foreach ($sections as $section_delta => $section) {
          $sections[$section_delta] = new Section(
            $section['layout_id'],
            $section['layout_settings'],
            array_map(function (array $component) {
              return (new SectionComponent(
                $component['uuid'],
                $component['region'],
                $component['configuration'],
                $component['additional']
              ))->setWeight($component['weight']);
            }, $section['components'])
          );
        }
      }
    }
    return parent::mapFromStorageRecords($records);
  }

}
