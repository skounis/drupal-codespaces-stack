<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\FieldTextExtractorInterface;

/**
 * A field text extractor plugin for text fields.
 */
#[FieldTextExtractor(
  id: "text",
  label: new TranslatableMarkup('Text'),
  field_types: [
    'title',
    'text',
    'text_with_summary',
    'text_long',
    'string',
    'string_long',
  ]
)]
class TextFieldExtractor implements FieldTextExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    if ($entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $textMeta = [];
    foreach ($entity->get($fieldName) as $delta => $fieldItem) {
      $textMeta[] = ['delta' => $delta] + $fieldItem->getValue();
    }
    return $textMeta;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(
    ContentEntityInterface $entity,
    string $fieldName,
    array $value,
  ) : void {
    $newValue = [];
    foreach ($value as $delta => $singleValue) {
      unset($singleValue['field_name'], $singleValue['field_type']);
      $newValue[$delta] = $singleValue;
      // Trim result if the field definition has a length limit.
      $field_definition = $entity->getFieldDefinition($fieldName);
      // Check if the field definition has a max length value.
      if ($field_definition instanceof FieldDefinitionInterface) {
        $settings = $field_definition->getSettings();
        // Retrieve the max length if it exists.
        $max_length = $settings['max_length'] ?? NULL;
        if ($max_length !== NULL) {
          $newValue[$delta]['value'] = mb_strimwidth(
            $newValue[$delta]['value'], 0, $max_length, '...');
        }
      }
    }
    $entity->set($fieldName, $newValue);
  }

  /**
   * {@inheritDoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    return $fieldDefinition->isTranslatable();
  }

}
