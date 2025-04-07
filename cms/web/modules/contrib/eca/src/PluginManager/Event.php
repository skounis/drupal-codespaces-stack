<?php

namespace Drupal\eca\PluginManager;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\eca\Annotation\EcaEvent;
use Drupal\eca\Plugin\ECA\Event\EventInterface;

/**
 * ECA event plugin manager.
 */
class Event extends DefaultPluginManager {

  /**
   * Constructor of the event plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ECA/Event',
      $namespaces,
      $module_handler,
      EventInterface::class,
      EcaEvent::class
    );
    $this->alterInfo('eca_event_info');
    $this->setCacheBackend($cache_backend, 'eca_event_plugins', ['eca_event_plugins']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function findDefinitions(): array {
    $definitions = parent::findDefinitions();

    // Build a mapping of system events to their according plugin.
    $system_event_mapping = [];
    foreach ($definitions as $id => $definition) {
      // Assert there must only be one plugin counterpart for each system event.
      if (isset($system_event_mapping[$definition['event_name']])) {
        throw new InvalidPluginDefinitionException($id, sprintf('There must be only one distinct plugin definition for each system event. Affected system event: %s', $definition['event_name']));
      }
      $system_event_mapping[$definition['event_name']] = $id;
    }

    // Store the mapping of system events along the definitions.
    $definitions['_mapping'] = $system_event_mapping;

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): ?array {
    $definitions = parent::getDefinitions();
    unset($definitions['_mapping']);
    return $definitions;
  }

  /**
   * Get the according plugin ID for a system event.
   *
   * @param string $event_name
   *   The name of the system event, that is usually passed along as second
   *   argument along the event object to the event dispatcher service for
   *   dispatching an event.
   *
   * @return string|null
   *   The according plugin ID, or NULL when there is no according plugin.
   */
  public function getPluginIdForSystemEvent(string $event_name): ?string {
    // @phpstan-ignore-next-line
    if (!isset($this->definitions)) {
      $this->getDefinitions();
    }
    return $this->definitions['_mapping'][$event_name] ?? NULL;
  }

}
