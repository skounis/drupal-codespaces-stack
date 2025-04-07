<?php

namespace Drupal\yoast_seo\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'yoast_seo' field type.
 *
 * @FieldType(
 *   id = "yoast_seo",
 *   label = @Translation("Real-time SEO status & focused keywords"),
 *   module = "yoast_seo",
 *   description = @Translation("The Real-time SEO status in points and the focused keywords."),
 *   default_widget = "yoast_seo_widget",
 *   default_formatter = "yoastseo_empty_formatter"
 * )
 */
class YoastSeoItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'status';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'status' => [
          'type' => 'varchar',
          'length' => 256,
          'not null' => FALSE,
        ],
        'focus_keyword' => [
          'type' => 'varchar',
          'length' => 256,
          'not null' => FALSE,
        ],
        'title' => [
          'type' => 'varchar',
          'length' => 1024,
          'not null' => FALSE,
        ],
        'description' => [
          'type' => 'varchar',
          'length' => 1024,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['status'] = DataDefinition::create('string')
      ->setLabel(t('Status'));
    $properties['focus_keyword'] = DataDefinition::create('string')
      ->setLabel(t('Focus Keyword'));
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(t('Edited title'));
    $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Edited description'));

    return $properties;
  }

}
