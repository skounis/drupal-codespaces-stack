<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Dispatches on event-based options selection.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class OptionsSelection extends FieldSelectionBase {

  /**
   * The field storage definition.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  public FieldStorageDefinitionInterface $fieldStorageDefinition;

  /**
   * The according entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface|null
   */
  public ?ContentEntityInterface $entity;

  /**
   * The current list of allowed values.
   *
   * @var array
   */
  public array $allowedValues;

  /**
   * Constructs a new event.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The according entity.
   * @param array $allowed_values
   *   The current list of allowed values.
   */
  public function __construct(FieldStorageDefinitionInterface $field_storage_definition, ?ContentEntityInterface $entity, array $allowed_values) {
    $this->fieldStorageDefinition = $field_storage_definition;
    $this->entity = $entity;
    $this->allowedValues = $allowed_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns TRUE if the event has an entity.
   *
   * @return bool
   *   TRUE, if an entity is available, FALSE otherwise.
   */
  public function hasEntity(): bool {
    return ($this->entity !== NULL);
  }

}
