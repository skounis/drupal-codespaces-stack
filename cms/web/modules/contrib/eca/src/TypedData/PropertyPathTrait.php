<?php

namespace Drupal\eca\TypedData;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * A trait for traversing through the property path of typed data objects.
 */
trait PropertyPathTrait {

  /**
   * Returns the property that is addressed by the given property path.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $object
   *   The object to start the traversal along the property path.
   * @param string $property_path
   *   The property path. This argument will be automatically normalized by
   *   using ::normalizePropertyPath().
   * @param array $options
   *   (optional) Options to use during path traversal. Following keys are
   *   available:
   *   - "auto_append": A boolean indicating whether auto-appending items to
   *     a list is allowed in case the traversal would stop otherwise. Default
   *     is not enabled (FALSE).
   *   - "auto_item": A boolean indicating to automatically use the
   *     main property or first item of a list in case the given property path
   *     did not directly specify a scalar property. Default is enabled (TRUE).
   *   - "access": A string that is the operation to check access for.
   *     By default, an access check regards "view" operation is checked. Set to
   *     FALSE to completely skip access checks.
   * @param array &$metadata
   *   (optional) Metadata that was collected along the traversal. Following
   *   keys are available:
   *   - "entities" holds a list of entities that are involved along the path.
   *   - "cache" holds collected cacheability metadata.
   *   - "access" holds the calculated access result.
   *   - "parts" holds the extracted parts of the property path as array.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The property target, or NULL if not found. the latter case can happen
   *   when either the property path does not exist, or the user has no access.
   */
  protected function getTypedProperty(TypedDataInterface $object, string $property_path, array $options = [], array &$metadata = []): ?TypedDataInterface {
    $property_path = $this->normalizePropertyPath($property_path);

    if (empty($property_path)) {
      // The source is always an entity. Within this action, it cannot be
      // updated without at least specifying a field name.
      return NULL;
    }

    $options += [
      'auto_append' => FALSE,
      'auto_item' => TRUE,
      'access' => 'view',
    ];
    $metadata += [
      'entities' => [],
      'cache' => new CacheableMetadata(),
      'access' => AccessResult::allowed(),
    ];

    $parts = explode('.', $property_path);
    $metadata['parts'] = $parts;
    $last_i = count($parts) - 1;
    $data = $object;
    foreach ($parts as $i => $property) {
      if ($data instanceof ComplexDataInterface) {
        $data = $this->getDataProperties($data, $property);
      }
      if ((($data instanceof \ArrayAccess) || is_array($data)) && isset($data[$property])) {
        $data = $data[$property];
        if ($data instanceof DataReferenceInterface) {
          // Directly jump to the contained target, if any is present.
          // @todo Should entities be auto-created here?
          if (!($data = $data->getTarget())) {
            return NULL;
          }
        }
      }
      elseif ($data instanceof ListInterface) {
        // Allow for auto-appending an item, in case the given argument
        // indicates that it either does not care (i.e. the current property
        // is not a digit) or points to the next free delta in the list.
        if (!ctype_digit($property)) {
          // Some input may skip the delta, e.g. body.value is treated as an
          // equivalent to body.0.value.
          if ((NULL === $data->first()) && $options['auto_append']) {
            $data->appendItem();
          }
          $data = $data->first();
          if ($data instanceof ComplexDataInterface) {
            $data = $this->getDataProperties($data, $property);
            if (isset($data[$property])) {
              $data = $data[$property];
            }
            else {
              return NULL;
            }
          }
        }
        elseif (($property == count($data)) && $options['auto_append']) {
          $data = $data->appendItem();
        }
        else {
          return NULL;
        }
      }
      else {
        return NULL;
      }
      if (($i === $last_i) && ($options['auto_item'])) {
        if ($data instanceof ListInterface) {
          if ((NULL === $data->first()) && $options['auto_append']) {
            $data->appendItem();
          }
          $data = $data->first();
        }
        if ($data instanceof ComplexDataInterface) {
          $main_property = $data->getDataDefinition()->getMainPropertyName();
          if ($main_property !== NULL) {
            $data = $data->get($main_property);
          }
        }
      }

      if ($data === NULL) {
        return NULL;
      }

      $value = $data->getValue();
      $entity = NULL;
      if (!($value instanceof EntityInterface) && method_exists($data, 'getEntity') && ($entity = $data->getEntity())) {
        $value = $entity;
      }
      if ($value instanceof EntityInterface && !in_array($entity, $metadata['entities'], TRUE)) {
        $metadata['entities'][] = $value;
      }

      // Perform access checks and add existing cacheability metadata.
      foreach ([$data, $value] as $subject) {
        if ($subject instanceof CacheableDependencyInterface) {
          $metadata['cache']->addCacheableDependency($subject);
        }
        if ($options['access'] && $subject instanceof AccessibleInterface) {
          // @todo Try to find a simpler solution path for access logic.
          // @see https://www.drupal.org/project/drupal/issues/3244585
          $op = ($subject instanceof FieldItemListInterface) && $options['access'] === 'update' ? 'edit' : $options['access'];
          $access_result = $subject->access($op, NULL, TRUE);
          if ($access_result instanceof CacheableDependencyInterface) {
            $metadata['cache']->addCacheableDependency($access_result);
          }
          $metadata['access'] = $metadata['access']->andIf($access_result);
          if (!$access_result->isAllowed()) {
            return NULL;
          }
        }
      }
    }
    if (empty($metadata['entities'])) {
      // Try to fetch at least one entity that was involved along the path.
      $root = $data->getRoot();
      if (method_exists($root, 'getEntity')) {
        $root = $root->getEntity();
        $metadata['entities'][] = $root;
      }
    }
    return $data;
  }

  /**
   * Normalizes a key that may be given by user input to a property path.
   *
   * @param string $key
   *   The key to normalize.
   *
   * @return string
   *   The normalized key.
   */
  protected function normalizePropertyPath(string $key): string {
    // Always use lowercase letters.
    $key = mb_strtolower(trim($key));

    if (!empty($key)) {
      if ($key[0] === '[' && $key[mb_strlen($key) - 1] === ']') {
        // Remove the brackets coming from Token syntax.
        $key = mb_substr($key, 1, -1);
      }
      if (mb_strpos($key, ':') !== FALSE) {
        // Convert token-like syntax into a (hopefully) valid property path.
        $key = str_replace(':', '.', $key);
      }
    }

    return $key;
  }

  /**
   * Helper method to get the properties of the given typed data object.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $data
   *   The typed data object.
   * @param string|int $property_name
   *   The targeted property name.
   *
   * @return array
   *   The properties, keyed by property name. May be empty.
   */
  protected function getDataProperties(ComplexDataInterface $data, $property_name): array {
    $properties = $data->getProperties(TRUE);
    $tdm = NULL;
    $definitions = [];
    if (!$properties || !isset($properties[$property_name])) {
      // When the targeted property name is missing, we lookup whether it is
      // allowed to add it on our own. Therefore we need the typed data manager
      // and knowledge about existing property definitions.
      $tdm = \Drupal::typedDataManager();
      $definitions = $data->getDataDefinition()->getPropertyDefinitions();
    }
    if (!$properties && $tdm !== NULL) {
      $values = $data->getValue();
      if (is_iterable($values)) {
        foreach ($values as $k => $v) {
          try {
            $properties[$k] = isset($definitions[$property_name]) ? $tdm->create($definitions[$property_name], $v, $k, $data) : DataTransferObject::create($v, $data, $k, FALSE);
          }
          catch (\InvalidArgumentException | MissingDataException $e) {
            // Do nothing, we are only interested in values that could
            // be successfully resolved.
          }
        }
      }
    }
    if (!isset($properties[$property_name])) {
      if ($tdm !== NULL && isset($definitions[$property_name])) {
        $properties[$property_name] = $tdm->create($definitions[$property_name], NULL, $property_name, $data);
      }
      elseif (empty($definitions)) {
        $properties[$property_name] = DataTransferObject::create(NULL, $data, $property_name, FALSE);
      }
    }
    return $properties;
  }

}
