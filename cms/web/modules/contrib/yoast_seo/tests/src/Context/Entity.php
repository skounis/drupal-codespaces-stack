<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides entity related steps for our Behat tests.
 */
class Entity implements Context {

  /**
   * Create one or more fields at the start of a test.
   *
   * @phpcs:disable Drupal.Files.LineLength.TooLong
   *
   * Creates fields the form:
   *   | entity_type | bundle  | type              | field_name | field_label | cardinality | form_widget                | field_formatter            |
   *   | node        | article | text_with_summary | body       | Body        | 1           | text_textarea_with_summary | text_textarea_with_summary |
   *   | node        | article | yoast_seo         | field_seo  | SEO         | 1           | yoast_seo                  |                            |
   *
   * @phpcs:enable Drupal.Files.LineLength.TooLong
   *
   * If cardinality is omitted it will default to one. Use -1 for unlimited
   * cardinality.
   *
   * If form_widget is omitted the field will not be visible on the form.
   *
   * If display is `1`, `yes`, `true`, then the field will be shown in the
   * default display view mode. If the value is `0`, `no`, `false`, ``, or
   * omitted then the field won't be displayed when rendering the entity.
   *
   * @Given field(s):
   */
  public function assertFields(TableNode $fields) : void {
    foreach ($fields->getHash() as $field) {
      assert(isset($field['entity_type']), "entity_type must be specified");
      assert(isset($field['bundle']), "bundle must be specified");
      assert(isset($field['type']), "type must be specified");
      assert(isset($field['field_name']), "field_name must be specified");
      assert(isset($field['field_label']), "field_label must be specified");

      // Look for or add the specified field to the requested entity bundle.
      if (!FieldStorageConfig::loadByName($field['entity_type'], $field['field_name'])) {
        $cardinality = $field['cardinality'] ?? 1;
        if ($cardinality === -1) {
          $cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
        }

        FieldStorageConfig::create([
          'field_name' => $field['field_name'],
          'type' => $field['type'],
          'entity_type' => $field['entity_type'],
          'cardinality' => $cardinality,
          'settings' => [],
        ])->save();
      }
      if (!FieldConfig::loadByName($field['entity_type'], $field['bundle'], $field['field_name'])) {
        FieldConfig::create([
          'field_name' => $field['field_name'],
          'entity_type' => $field['entity_type'],
          'bundle' => $field['bundle'],
          'label' => $field['field_label'],
          'settings' => [],
        ])->save();
      }

      // @phpstan-ignore-next-line
      $display_repository = \Drupal::service('entity_display.repository');

      $form_display = $display_repository->getFormDisplay($field['entity_type'], $field['bundle']);
      if (!empty($field['form_widget'])) {
        $form_widget_settings = !empty($field['form_widget_settings']) ? json_decode($field['form_widget_settings'], TRUE, 512, JSON_THROW_ON_ERROR) : [];

        $form_display->setComponent($field['field_name'], [
          'type' => $field['form_widget'],
          'weight' => 0,
          'settings' => $form_widget_settings,
        ]);
      }
      else {
        $form_display->removeComponent($field['field_name']);
      }
      $form_display->save();

      $view_display = $display_repository->getViewDisplay($field['entity_type'], $field['bundle']);
      if (!empty($field['field_formatter'])) {
        $view_display->setComponent($field['field_name'], [
          'type' => $field['field_formatter'],
          'weight' => 0,
        ]);
      }
      else {
        $view_display->removeComponent(($field['field_name']));
      }
      $view_display->save();

    }
  }

}
