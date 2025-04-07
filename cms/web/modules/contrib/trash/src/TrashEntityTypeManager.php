<?php

namespace Drupal\trash;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;

/**
 * Provides custom storage handlers for trash-supported entity types.
 */
class TrashEntityTypeManager extends EntityTypeManager {

  /**
   * Contains instantiated storage handlers keyed by entity type.
   *
   * @var array
   */
  protected $storageHandlers = [];

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();
    PhpStorageFactory::get('trash')->deleteAll();
    // @phpstan-ignore-next-line
    \Drupal::state()->delete('trash.class_suffix');
    $this->storageHandlers = [];
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE) {
    parent::useCaches($use_caches);
    if (!$use_caches) {
      PhpStorageFactory::get('trash')->deleteAll();
      // @phpstan-ignore-next-line
      \Drupal::state()->delete('trash.class_suffix');
      $this->storageHandlers = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($entity_type_id) {
    return $this->getHandler($entity_type_id, 'storage');
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler($entity_type_id, $handler_type) {
    if ($handler_type !== 'storage') {
      return parent::getHandler($entity_type_id, $handler_type);
    }

    if (!isset($this->storageHandlers[$entity_type_id])) {
      $definition = $this->getDefinition($entity_type_id);
      $class = $definition->getHandlerClass($handler_type);
      if (!$class) {
        throw new InvalidPluginDefinitionException($entity_type_id, sprintf('The "%s" entity type did not specify a %s handler.', $entity_type_id, $handler_type));
      }
      $this->storageHandlers[$entity_type_id] = $this->createHandlerInstance($class, $definition);
    }

    return $this->storageHandlers[$entity_type_id];
  }

  /**
   * {@inheritdoc}
   */
  public function createHandlerInstance($class, ?EntityTypeInterface $definition = NULL) {
    // @phpstan-ignore-next-line
    if (\Drupal::service('trash.manager')->isEntityTypeEnabled($definition)
      && is_subclass_of($class, SqlEntityStorageInterface::class)) {
      $class = _trash_generate_storage_class($class);
    }

    return parent::createHandlerInstance($class, $definition);
  }

}
