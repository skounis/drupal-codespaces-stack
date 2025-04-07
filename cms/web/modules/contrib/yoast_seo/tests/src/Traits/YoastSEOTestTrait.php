<?php

namespace Drupal\Tests\yoast_seo\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides common functionality for the Yoast SEO test classes.
 */
trait YoastSEOTestTrait {

  /**
   * Creates a field of a yoast seo field storage on the specified bundle.
   *
   * @param string $entity_type
   *   The type of entity the field will be attached to.
   * @param string $bundle
   *   The bundle name of the entity the field will be attached to.
   * @param string $field_name
   *   The name of the field; if it already exists, a new instance of the
   *   existing field will be created.
   * @param string $field_label
   *   The label of the field.
   * @param int $cardinality
   *   The cardinality of the field.
   */
  protected function createYoastSeoField($entity_type, $bundle, $field_name, $field_label, $cardinality = 1) {
    // Look for or add the specified field to the requested entity bundle.
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'type' => 'yoast_seo',
        'entity_type' => $entity_type,
        'cardinality' => $cardinality,
        'settings' => [],
      ])->save();
    }
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_label,
        'settings' => [],
      ])->save();
    }
  }

}
