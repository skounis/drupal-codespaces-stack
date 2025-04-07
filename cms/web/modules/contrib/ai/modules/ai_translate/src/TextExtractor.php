<?php

namespace Drupal\ai_translate;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines text extractor service.
 */
class TextExtractor implements TextExtractorInterface {

  /**
   * Text extractor plugins, keyed by field type.
   *
   * @var array \Drupal\ai_translate\FieldTextExtractorInterface[]
   */
  protected $plugins = [];

  /**
   * Creates an TextExtractor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\ai_translate\FieldTextExtractorPluginManagerInterface $extractorManager
   *   Field text extractor plugin manager.
   */
  final public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FieldTextExtractorPluginManagerInterface $extractorManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function extractTextMetadata(ContentEntityInterface $entity, array $parents = []) : array {
    $metadata = [];
    foreach ($this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(), $entity->bundle()) as $field_name => $field_definition) {
      $fieldType = $field_definition->getType();
      if (!isset($this->plugins[$fieldType])) {
        // Use FALSE instead of NULL to prevent re-getting missing plugin.
        $this->plugins[$fieldType] = $this->extractorManager
          ->getExtractor($fieldType) ?? FALSE;
      }
      if (!$this->shouldExtract($entity, $field_definition)) {
        continue;
      }
      $fieldMeta = $this->plugins[$fieldType]->extract($entity, $field_name);
      if ($fieldMeta) {
        foreach ($fieldMeta as $meta) {
          $fieldParents = [$field_name];
          if (isset($meta['delta'])) {
            $fieldParents[] = $meta['delta'];
            unset($meta['delta']);
          }
          if (isset($meta['parents'])) {
            $fieldParents = array_merge($fieldParents, $meta['parents']);
          }
          $meta['parents'] = $fieldParents;
          $metadata[] = [
            'field_name' => $field_name,
            'field_type' => $fieldType,
          ] + $meta;
        }
      }
    }
    return $metadata;
  }

  /**
   * {@inheritDoc}
   */
  public function insertTextMetadata(ContentEntityInterface $entity, array $metadata): void {
    $nestedValue = [];
    // First, unflatten translated text metadata, and remove original values.
    foreach ($metadata as $singleValue) {
      $parents = $singleValue['parents'];
      unset($singleValue['parents']);
      $singleValue['value'] = $singleValue['translated'];
      unset($singleValue['translated']);
      NestedArray::setValue($nestedValue, $parents, $singleValue);
    }
    foreach ($nestedValue as $fieldName => $fieldValue) {
      $field = $entity->get($fieldName);
      $extractor = $this->extractorManager->getExtractor(
        $field->getFieldDefinition()->getType()
      );
      // This shouldn't happen.
      if (!$extractor) {
        continue;
      }
      $extractor->setValue($entity, $fieldName, $fieldValue);
    }
  }

  /**
   * Determines if a field should be extracted for translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field should be extracted, FALSE otherwise.
   */
  protected function shouldExtract(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
  ): bool {
    if ($fieldDefinition->isComputed()
    || $fieldDefinition->isReadOnly()) {
      return FALSE;
    }
    $fieldName = $fieldDefinition->getName();
    $fieldType = $fieldDefinition->getType();
    if (empty($this->plugins[$fieldType])) {
      return FALSE;
    }
    if ($fieldDefinition->getName() === $entity->getEntityType()->getKey('label')) {
      return $fieldDefinition->isTranslatable();
    }
    // @todo better way to find fields that should be translatable?
    static $supportedFieldNames = [
      'body',
      'info',
      '^field_',
      '^layout_builder_',
    ];
    foreach ($supportedFieldNames as $pattern) {
      if (preg_match("/$pattern/", $fieldName)) {
        return $this->plugins[$fieldType]->shouldExtract(
          $entity, $fieldDefinition);
      }
    }
    return FALSE;
  }

}
