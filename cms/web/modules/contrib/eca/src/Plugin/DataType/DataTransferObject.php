<?php

namespace Drupal\eca\Plugin\DataType;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Drupal\eca\TypedData\DataTransferObjectDefinition;

/**
 * Defines the "dto" data type.
 *
 * A Data Transfer Object (DTO) allows attachment of arbitrary properties.
 * A DTO can also be used as a list: items may be dynamically added by using '+'
 * and removed by using '-'. Example: $dto->set('+', $value).
 *
 * @DataType(
 *   id = "dto",
 *   label = @Translation("Data Transfer Object"),
 *   description = @Translation("Data Transfer Objects (DTOs) which may contain arbitrary and user-defined properties of data."),
 *   definition_class = "\Drupal\eca\TypedData\DataTransferObjectDefinition"
 * )
 */
class DataTransferObject extends Map {

  /**
   * A manually set string representation of this object.
   *
   * @var string|null
   */
  protected ?string $stringRepresentation = NULL;

  /**
   * Creates a new instance of a DTO.
   *
   * @param mixed $value
   *   (optional) The value to set, in conformance to ::setValue(). May also
   *   be a content entity, whose fields will be used. When the given value is a
   *   scalar, it will be set in conformance to ::setStringRepresentation().
   * @param \Drupal\Core\TypedData\TypedDataInterface|null $parent
   *   (optional) If known, the parent object.
   * @param string|null $name
   *   (optional) If the parent is given, the property name of the parent.
   * @param bool $notify
   *   (optional) Whether to notify the parent object of the change.
   *
   * @return \Drupal\eca\Plugin\DataType\DataTransferObject
   *   The DTO instance.
   */
  public static function create(mixed $value = NULL, ?TypedDataInterface $parent = NULL, ?string $name = NULL, bool $notify = TRUE): DataTransferObject {
    $manager = \Drupal::typedDataManager();
    if ($parent && $name) {
      /**
       * @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto
       */
      $dto = $manager->createInstance('dto', [
        'data_definition' => DataTransferObjectDefinition::create('dto'),
        'name' => $name,
        'parent' => $parent,
      ]);
    }
    else {
      /**
       * @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto
       */
      $dto = $manager->create(DataTransferObjectDefinition::create('dto'));
    }
    if (isset($value)) {
      if ($value instanceof EntityInterface) {
        $dto->setStringRepresentation($value->id());
      }
      elseif ($value instanceof Config) {
        $dto->setStringRepresentation($value->getName());
      }
      if (is_scalar($value)) {
        $dto->setStringRepresentation($value);
      }
      else {
        $dto->setValue($value, $notify);
      }
    }
    return $dto;
  }

  /**
   * Creates a DTO from user input.
   *
   * User input may be a Yaml-formatted hash of values, or an unformatted
   * sequence of values, separated with commas and optionally with a colon for
   * keyed values. Plain values without separator (comma or new line) will use
   * the string representation instead of an array of properties.
   *
   * @param string $user_input
   *   The user input as string.
   *
   * @return \Drupal\eca\Plugin\DataType\DataTransferObject
   *   A DTO instance, holding values from the user input.
   */
  public static function fromUserInput(string $user_input): DataTransferObject {
    if (mb_strpos($user_input, PHP_EOL)) {
      try {
        $values = Yaml::decode($user_input);
        if (is_string($values)) {
          // Only care for trying conversion of nested structures. For any other
          // values, apply the other section below.
          $values = [];
        }
      }
      catch (InvalidDataTypeException) {
        $values = [];
      }
    }
    else {
      $values = [];
    }
    if (empty($values) && ($user_input !== '')) {
      $option = strtok($user_input, "," . PHP_EOL);
      while ($option !== FALSE) {
        $option = trim($option);
        [$key, $value] = array_merge(explode(':', $option, 2), [$option]);
        $key = trim($key);
        $value = trim($value);
        if (mb_substr($key, 0, 1) === '[' && mb_substr($value, -1, 1) === ']') {
          // Prevent tokens from being split off.
          $key = $value = $option;
        }
        if ($key !== '' && $value !== '') {
          $values[$key] = $value;
        }
        $option = strtok("," . PHP_EOL);
      }
    }

    // Use the string representation directly if no sequence was provided.
    if ((count($values) === 1) && (key($values) === current($values))) {
      $values = current($values);
    }

    return static::create($values);
  }

  /**
   * Shorthand method for building an array from user input.
   *
   * @param string $user_input
   *   The user input as string.
   *
   * @return array
   *   The built array, holding values from the user input.
   *
   * @see ::fromUserInput
   */
  public static function buildArrayFromUserInput(string $user_input): array {
    return static::fromUserInput($user_input)->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    // Make sure that the data definition reflects dynamically added properties.
    $this->definition = DataTransferObjectDefinition::create($definition->getDataType(), $this);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $values = [];
    foreach ($this->getProperties() as $name => $property) {
      $values[$name] = $property instanceof ComplexDataInterface ? $property->toArray() : $property->getValue();
    }
    if (empty($values) && isset($this->stringRepresentation)) {
      $values[] = $this->stringRepresentation;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $value = [];
    // Build up an associative array that holds both the data types and the
    // corresponding contained values, so that the property list holding
    // typed data objects may be restored at any subsequent processing.
    foreach ($this->properties as $name => $property) {
      $definition = $property->getDataDefinition();
      if (!$definition->isComputed()) {
        $value['types'][$name] = $definition->getDataType();
        $value['values'][$name] = $property->getValue();
      }
    }
    if (isset($this->stringRepresentation)) {
      if ($value) {
        $value['_string_representation'] = $this->stringRepresentation;
      }
      else {
        $value = $this->stringRepresentation;
      }
    }
    return $value;
  }

  /**
   * Overrides \Drupal\Core\TypedData\Plugin\DataType\Map::setValue().
   *
   * A DTO allows arbitrary properties. In order to know about the correct data
   * types of given properties, passed values should be typed data objects.
   * Alternatively, scalar values may be passed in directly in case it's also
   * not that critical that a given value may be (wrongly) treated as a string.
   * Otherwise, an additional types key should be provided (see description of
   * the $values argument).
   *
   * @param mixed|null $values
   *   An array of property values as typed data objects, scalars or entities.
   *   Alternatively, if typed data objects are not available at this point, the
   *   values may be an associative array keyed by 'types' and 'values'. Both
   *   array values are a sequence that match with their array keys,
   *   which are in turn property names. Set to NULL to make this object empty.
   * @param bool $notify
   *   (optional) Whether to notify the parent object of the change. Defaults to
   *   TRUE. If a property is updated from a parent object, set it to FALSE to
   *   avoid being notified again.
   */
  public function setValue($values, $notify = TRUE): void {
    if ($values instanceof TypedDataInterface) {
      if (($values instanceof TraversableTypedDataInterface) && ($elements = static::traverseElements($values))) {
        $values = $elements;
      }
      else {
        $values = $values->getValue();
      }
    }
    if ($values instanceof EntityInterface) {
      $values = $values->getTypedData()->getProperties();
    }
    elseif ($values instanceof Config) {
      /**
       * @var \Drupal\Core\TypedData\TraversableTypedDataInterface $typed_config
       */
      $typed_config = \Drupal::service('config.typed')->createFromNameAndData($values->getName(), $values->getRawData());
      $values = static::traverseElements($typed_config);
    }
    if (is_null($values)) {
      // Shortcut to make this DTO empty.
      $this->stringRepresentation = NULL;
      $this->properties = [];
      $this->values = [];
    }
    elseif (is_scalar($values) || ($values instanceof MarkupInterface)) {
      // Internally forward this argument to set it as string representation.
      // This is not officially allowed by this method, but included here
      // to reduce possible hurdles when working with a DTO.
      $this->setStringRepresentation($values);
    }
    elseif (!is_array($values)) {
      throw new \InvalidArgumentException("Invalid values given. Values must be represented as an associative array.");
    }
    else {
      if (isset($values['_string_representation'])) {
        $this->setStringRepresentation($values['_string_representation']);
        unset($values['_string_representation']);
      }
      if (empty($values['types']) || empty($values['values'])) {
        foreach ($values as $name => $value) {
          if (!($value instanceof TypedDataInterface)) {
            if ($value instanceof EntityInterface) {
              $values[$name] = $this->wrapEntityValue($name, $value);
            }
            elseif (is_scalar($value)) {
              $values[$name] = $this->wrapScalarValue($name, $value);
            }
            elseif (is_iterable($value)) {
              $values[$name] = $this->wrapIterableValue($name, $value);
            }
            elseif (is_null($value)) {
              unset($values[$name]);
            }
            elseif ($value instanceof MarkupInterface) {
              $values[$name] = $this->wrapScalarValue($name, (string) $value);
            }
            elseif ($value instanceof Url) {
              $values[$name] = $this->wrapUrlValue($name, $value);
            }
            elseif (is_object($value) && method_exists($value, '__toString')) {
              $values[$name] = $this->wrapAnyValue($name, $value);
            }
            else {
              throw new \InvalidArgumentException("Invalid values given. Values must be of scalar types, entities, stringable or typed data objects.");
            }
          }
        }
      }
      else {
        $manager = $this->getTypedDataManager();
        $instances = [];
        foreach ($values['types'] as $name => $type) {
          $instance = $manager->createInstance($type, [
            'data_definition' => $manager->createDataDefinition($type),
            'name' => $name,
            'parent' => $this,
          ]);
          $instance->setValue($values['values'][$name], FALSE);
          $instances[$name] = $instance;
        }
        $values = $instances;
      }
      // Update any existing property objects.
      foreach ($this->properties as $name => $property) {
        if (isset($values[$name])) {
          $property->setValue($values[$name]->getValue(), FALSE);
        }
        else {
          // Property does not exist anymore, thus remove it.
          unset($this->properties[$name]);
        }
        // Remove the value from $this->values to ensure it does not contain any
        // value for computed properties.
        unset($this->values[$name]);
      }
      // Add new properties.
      $this->properties += $values;
    }

    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * Set a string representation of this object.
   *
   * @param mixed $value
   *   A scalar value.
   */
  public function setStringRepresentation(mixed $value): void {
    $this->stringRepresentation = is_null($value) ? NULL : (string) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getString(): ?string {
    if (isset($this->stringRepresentation)) {
      return $this->stringRepresentation;
    }

    if (isset($this->properties['#type']) || isset($this->properties['#theme'])) {
      // Attached data is a renderable array, so render it.
      $renderer = static::renderer();
      $build = $this->toArray();
      if ($renderer->hasRenderContext()) {
        return $renderer->render($build);
      }
      return $renderer->executeInRenderContext(new RenderContext(), static function () use (&$build, $renderer) {
        return $renderer->render($build);
      });
    }

    $values = [];
    $is_assoc = FALSE;
    foreach ($this->getProperties() as $name => $property) {
      $value = $property instanceof ComplexDataInterface ? $property->toArray() : $property->getValue();
      if (is_object($value)) {
        // Objects are not supported for being encoded to Yaml.
        $value = $property->getString();
      }
      if (($value === NULL) || ($value === '') || (is_iterable($value) && !count($value))) {
        // Skip empty items.
        continue;
      }
      if (is_array($value)) {
        // Convert entities to arrays for Yaml encoding below.
        foreach ($value as $k => $v) {
          if ($v instanceof EntityInterface) {
            $value[$k] = $v->toArray();
          }
        }
      }
      if (is_int($name) || ctype_digit($name)) {
        $values[] = $value;
      }
      else {
        $values[$name] = $value;
        if ($name !== $value) {
          $is_assoc = TRUE;
        }
      }
    }
    if (!$is_assoc) {
      $values = array_values($values);
    }
    return $values ? Yaml::encode($values) : '';
  }

  /**
   * Implements magic __toString() method.
   */
  public function __toString(): string {
    return $this->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE): array {
    $properties = [];
    foreach ($this->properties as $name => $property) {
      $definition = $property->getDataDefinition();
      if ($include_computed || !$definition->isComputed()) {
        $properties[$name] = $property;
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function writePropertyValue($property_name, mixed $value): void {
    if ($property_name === '-') {
      if ($value === NULL) {
        array_pop($this->properties);
      }
      else {
        foreach ($this->properties as $name => $property) {
          if ($property === $value || $property->getValue() === $value) {
            unset($this->properties[$name]);
            if (is_int($name) || ctype_digit($name)) {
              $this->rekey($name);
            }
          }
        }
      }
    }
    elseif ($value instanceof TypedDataInterface) {
      if (isset($this->properties[$property_name])) {
        $this->properties[$property_name]->setValue($value->getValue());
      }
      elseif ($property_name === '+') {
        $this->properties[] = $value;
      }
      else {
        $this->properties[$property_name] = $value;
        // @todo $property name can never be integer, can it?
        // @phpstan-ignore-next-line
        if (is_int($property_name) || ctype_digit((string) $property_name)) {
          $this->rekey((int) $property_name);
        }
      }
    }
    elseif ($value === NULL) {
      // When receiving NULL as unwrapped $value, then handle this just like
      // removing the property from the list.
      unset($this->properties[$property_name]);
      // @todo $property name can never be integer, can it?
      // @phpstan-ignore-next-line
      if (is_int($property_name) || ctype_digit((string) $property_name)) {
        $this->rekey((int) $property_name);
      }
    }
    elseif ($value instanceof EntityInterface) {
      $this->writePropertyValue($property_name, $this->wrapEntityValue($property_name, $value));
    }
    elseif ($value instanceof Config) {
      $this->writePropertyValue($property_name, $this->wrapConfigValue($property_name, $value));
    }
    elseif (is_scalar($value)) {
      $this->writePropertyValue($property_name, $this->wrapScalarValue($property_name, $value));
    }
    elseif (is_iterable($value)) {
      $this->writePropertyValue($property_name, $this->wrapIterableValue($property_name, $value));
    }
    else {
      throw new \InvalidArgumentException("Invalid value given. Value must be of a scalar type, an entity or a typed data object.");
    }
  }

  /**
   * Magic method: Gets a property value.
   *
   * @param int|string $name
   *   The name of the property to get; e.g., 'title' or 'name'.
   *
   * @return mixed
   *   The property value.
   *
   * @throws \InvalidArgumentException
   *   If a non-existent property is accessed.
   */
  public function __get(int|string $name) {
    // There is either a property object or a plain value - possibly for a
    // not-defined property. If we have a plain value, directly return it.
    if (isset($this->properties[$name])) {
      return $this->properties[$name] instanceof PrimitiveInterface ? $this->properties[$name]->getValue() : $this->properties[$name];
    }
  }

  /**
   * Magic method: Sets a property value.
   *
   * @param int|string $name
   *   The name of the property to set; e.g., 'title' or 'name'.
   * @param mixed $value
   *   The value as typed data object to set, or NULL to unset the property.
   *
   * @throws \InvalidArgumentException
   *   If the given argument is not typed data or not NULL.
   */
  public function __set(int|string $name, mixed $value) {
    $this->set($name, $value);
  }

  /**
   * Magic method: Determines whether a property is set.
   *
   * @param int|string $name
   *   The name of the property to get; e.g., 'title' or 'name'.
   *
   * @return bool
   *   Returns TRUE if the property exists and is set, FALSE otherwise.
   */
  public function __isset(int|string $name) {
    if (isset($this->properties[$name])) {
      return $this->properties[$name]->getValue() !== NULL;
    }
    return FALSE;
  }

  /**
   * Magic method: Unsets a property.
   *
   * @param int|string $name
   *   The name of the property to get; e.g., 'title' or 'name'.
   */
  public function __unset(int|string $name) {
    if ($this->definition->getPropertyDefinition($name)) {
      $this->set($name, NULL);
    }
    else {
      // Explicitly unset the property in $this->values if a non-defined
      // property is unset, such that its key is removed from $this->values.
      unset($this->values[$name]);
    }
  }

  /**
   * Saves contained data, that belongs to a saveable resource.
   *
   * This operation is being performed as one database transaction.
   */
  public function saveData(): void {
    if (!($saveables = $this->getSaveables())) {
      return;
    }

    $transaction = static::databaseConnection()->startTransaction();
    foreach ($saveables as $saveable) {
      try {
        $saveable->save();
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
  }

  /**
   * Deletes contained data, that belongs to a saveable resource.
   *
   * This operation is being performed as one database transaction.
   */
  public function deleteData(): void {
    if (!($saveables = $this->getSaveables())) {
      return;
    }

    $transaction = static::databaseConnection()->startTransaction();
    foreach ($saveables as $saveable) {
      try {
        $saveable->delete();
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
  }

  /**
   * Get contained data items, that can be saved.
   *
   * @return array
   *   The saveable data items.
   */
  public function getSaveables(): array {
    $saveables = [];
    foreach ($this->properties as $property) {
      $value = $property->getValue();
      if ((($value instanceof EntityInterface) || (($value instanceof Config) && !($value instanceof ImmutableConfig))) && !in_array($value, $saveables, TRUE)) {
        $saveables[] = $value;
        continue;
      }
      $parent = NULL;
      while (($property->getParent() !== $parent) && ($parent = $property->getParent())) {
        $parent_value = $parent->getValue();
        if ((($parent_value instanceof EntityInterface) || (($parent_value instanceof Config) && !($parent_value instanceof ImmutableConfig))) && !in_array($parent_value, $saveables, TRUE)) {
          $saveables[] = $parent_value;
          break;
        }
      }
    }
    return $saveables;
  }

  /**
   * Shift the first item from the beginning of the object's list of properties.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The removed item, or NULL if the DTO is empty.
   */
  public function shift(): ?TypedDataInterface {
    $properties = $this->properties;
    reset($properties);
    $key = key($properties);
    $item = array_shift($this->properties);
    if (is_int($key) || ctype_digit((string) $key)) {
      $this->rekey($key);
    }
    return $item;
  }

  /**
   * Pop the last item from the end of the object's list of properties.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The removed item, or NULL if the DTO is empty.
   */
  public function pop(): ?TypedDataInterface {
    return array_pop($this->properties);
  }

  /**
   * Remove the given value from the object's list of properties.
   *
   * @param mixed $value
   *   The value to remove.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The removed item, or NULL if the DTO does not contain the given value.
   */
  public function remove(mixed $value): ?TypedDataInterface {
    $item = NULL;
    foreach ($this->properties as $name => $property) {
      $property_value = $property->getValue();
      $value_matches = ($property_value === $value || $property === $value);
      if (!$value_matches && ($value instanceof EntityInterface) && ($property_value instanceof EntityInterface)) {
        // Many times, entity objects are cloned. Take another look, whether the
        // identifier matches.
        $identifier = $identifier ?? ($value->uuid() ?? $value->id());
        $value_matches = isset($identifier) && ($identifier === ($property_value->uuid() ?? $property_value->id()))
          && ($value->language()->getId() === $property_value->language()->getId())
          // @phpstan-ignore-next-line
          && (!($value instanceof RevisionableInterface) || ($value->getRevisionId() === $property_value->getRevisionId()))
          && ($value->getEntityTypeId() === $property_value->getEntityTypeId());
      }
      if ($value_matches) {
        $item = $this->properties[$name];
        unset($this->properties[$name]);
        if (is_int($name) || ctype_digit($name)) {
          $this->rekey($name);
        }
      }
    }
    return $item;
  }

  /**
   * Remove an item from the object's list of properties by the given name.
   *
   * @param int|string $name
   *   The property name.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The removed item, or NULL if the DTO does not contain an item by the
   *   given property name.
   */
  public function removeByName(int|string $name): ?TypedDataInterface {
    $item = NULL;
    if (isset($this->properties[$name])) {
      $item = $this->properties[$name];
      unset($this->properties[$name]);
      if (is_int($name) || ctype_digit($name)) {
        $this->rekey($name);
      }
    }
    return $item;
  }

  /**
   * Adds a value to the beginning of the object's list of properties.
   *
   * @param mixed $value
   *   The value to add, preferable as typed data or an entity.
   *
   * @return int
   *   The index of the added value.
   */
  public function unshift(mixed $value): int {
    $index = $this->push($value);
    $property = $this->properties[$index];
    unset($this->properties[$index]);
    array_unshift($this->properties, $property);
    $properties = $this->properties;
    reset($properties);
    $index = key($properties);
    $this->rekey();
    return $index;
  }

  /**
   * Pushes a value to the end of the object's list of properties.
   *
   * @param mixed $value
   *   The value to add, preferable as typed data or an entity.
   *
   * @return int
   *   The index of the added value.
   */
  public function push(mixed $value): int {
    $properties = $this->properties;
    $properties[] = $value;
    end($properties);
    $index = key($properties);
    $this->writePropertyValue($index, $value);
    $this->rekey();
    return $index;
  }

  /**
   * Returns the number of property items.
   *
   * @return int
   *   The number of property items.
   */
  public function count(): int {
    return count($this->properties);
  }

  /**
   * {@inheritdoc}
   *
   * Also considers the string representation for being empty.
   */
  public function isEmpty(): bool {
    return (is_null($this->stringRepresentation) || $this->stringRepresentation === '') && parent::isEmpty();
  }

  /**
   * Wraps the scalar value by a Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param mixed $value
   *   The scalar value.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapScalarValue(int|string $name, mixed $value): TypedDataInterface {
    $manager = $this->getTypedDataManager();
    $scalar_type = 'string';
    if (is_numeric($value)) {
      $scalar_type = is_int($value) || ctype_digit((string) $value) ? 'integer' : 'float';
    }
    elseif (is_bool($value)) {
      $scalar_type = 'boolean';
    }
    $instance = $manager->createInstance($scalar_type, [
      'data_definition' => $manager->createDataDefinition($scalar_type),
      'name' => $name,
      'parent' => $this,
    ]);
    $instance->setValue($value, FALSE);
    return $instance;
  }

  /**
   * Wraps the entity by a Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param \Drupal\Core\Entity\EntityInterface $value
   *   The entity.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapEntityValue(int|string $name, EntityInterface $value): TypedDataInterface {
    $manager = $this->getTypedDataManager();
    $instance = $manager->createInstance('entity', [
      'data_definition' => EntityDataDefinition::create($value->getEntityTypeId(), $value->bundle()),
      'name' => $name,
      'parent' => $this,
    ]);
    $instance->setValue($value, FALSE);
    return $instance;
  }

  /**
   * Wraps the config by a Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param \Drupal\Core\Config\Config $value
   *   The config.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapConfigValue(int|string $name, Config $value) : TypedDataInterface {
    /** @var \Drupal\Core\Config\TypedConfigManager $manager */
    $manager = \Drupal::service('config.typed');
    /** @var \Drupal\Core\TypedData\TraversableTypedDataInterface $typed_config */
    $typed_config = $manager->createFromNameAndData($value->getName(), $value->getRawData());
    return $manager->create($typed_config->getDataDefinition(), $value->getRawData(), $name, $this);
  }

  /**
   * Wraps an iterable value by a Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param mixed $value
   *   The iterable value.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapIterableValue(int|string $name, mixed $value): TypedDataInterface {
    $instance = static::create(NULL, $this, $name, FALSE);
    foreach ($value as $k => $v) {
      $instance->set($k, $v, FALSE);
    }
    return $instance;
  }

  /**
   * Wraps a URL by a Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param \Drupal\Core\Url $value
   *   The URL value.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapUrlValue(int|string $name, Url $value): TypedDataInterface {
    $manager = $this->getTypedDataManager();
    $instance = $manager->createInstance('eca_url', [
      'data_definition' => $manager->createDataDefinition('eca_url'),
      'name' => $name,
      'parent' => $this,
    ]);
    $instance->setValue($value, FALSE);
    return $instance;
  }

  /**
   * Wraps any unspecified value by a non-specific ("any") Typed Data object.
   *
   * @param int|string $name
   *   The property name.
   * @param mixed $value
   *   The unspecified value.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The Typed Data object.
   */
  protected function wrapAnyValue(int|string $name, mixed $value): TypedDataInterface {
    $manager = $this->getTypedDataManager();
    $instance = $manager->createInstance('any', [
      'data_definition' => $manager->createDataDefinition('any'),
      'name' => $name,
      'parent' => $this,
    ]);
    $instance->setValue($value, FALSE);
    return $instance;
  }

  /**
   * Renumbers the items in the property list.
   *
   * @param int $from_index
   *   Optionally, the index at which to start the renumbering, if it is known
   *   that items before that can safely be skipped (for example, when removing
   *   an item at a given index).
   */
  protected function rekey(int $from_index = 0): void {
    $assoc = [];
    $sequence = [];
    foreach ($this->properties as $p_name => $p_val) {
      if (is_int($p_name) || ctype_digit($p_name)) {
        $sequence[] = $p_val;
      }
      else {
        $assoc[$p_name] = $p_val;
      }
    }
    $this->properties = array_merge($assoc, $sequence);
    // Each item holds its own index as a "name", it needs to be updated
    // according to the new list indexes.
    $countSequence = count($sequence);
    for ($i = $from_index; $i < $countSequence; $i++) {
      $this->properties[$i]->setContext((string) $i, $this);
    }
  }

  /**
   * Helper method to traverse and collect the traversed elements.
   *
   * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $traversable
   *   The traversable object.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface[]
   *   The traversed elements.
   */
  protected static function traverseElements(TraversableTypedDataInterface $traversable): array {
    $elements = [];
    foreach ($traversable as $key => $element) {
      $elements[$key] = $element;
    }
    return $elements;
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected static function databaseConnection(): Connection {
    return \Drupal::database();
  }

  /**
   * Get the renderer.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer.
   */
  protected static function renderer(): RendererInterface {
    return \Drupal::service('renderer');
  }

}
