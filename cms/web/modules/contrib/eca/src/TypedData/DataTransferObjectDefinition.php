<?php

namespace Drupal\eca\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * A typed data definition class for describing Data Transfer Objects (DTOs).
 */
final class DataTransferObjectDefinition extends ComplexDataDefinitionBase {

  /**
   * The DTO instance, if any given.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $dto;

  /**
   * Creates the data definition for Data Transfer Objects.
   *
   * To get to know the contained properties of a DTO, the instance of that
   * object needs to be provided as second argument.
   *
   * @param string $type
   *   The data type of the data, usually "dto".
   * @param \Drupal\eca\Plugin\DataType\DataTransferObject|null $dto
   *   (Optional) The data transfer object.
   *
   * @return static
   *   The data definition for the given dto.
   */
  public static function create($type, ?DataTransferObject $dto = NULL): DataTransferObjectDefinition {
    $instance = new self(['type' => $type]);
    $instance->dto = $dto;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $property_definitions = [];
    if (isset($this->dto)) {
      foreach ($this->dto->getProperties(TRUE) as $name => $property) {
        $property_definitions[$name] = $property->getDataDefinition();
      }
    }
    return $property_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataType() {
    return $this->definition['type'];
  }

}
