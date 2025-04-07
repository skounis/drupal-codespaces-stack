<?php

namespace Drupal\eca\Event;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Helper for triggering ECA-related events.
 */
class TriggerEvent {

  /**
   * The event plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $eventPluginManager;

  /**
   * An event dispatcher instance to use for ECA-related events.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The TriggerEvent constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $event_plugin_manager
   *   The event plugin manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   An event dispatcher instance to use for ECA-related events.
   */
  public function __construct(PluginManagerInterface $event_plugin_manager, EventDispatcherInterface $event_dispatcher) {
    $this->eventPluginManager = $event_plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Dispatches an event by using definitions from the given plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID of the ECA-related event ("EcaEvent").
   * @param mixed &$args
   *   Arguments that shall be passed to the constructor of the event object.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event|null
   *   The dispatched event, or NULL if no event was dispatched.
   */
  public function dispatchFromPlugin(string $plugin_id, &...$args): Event|null {
    try {
      /**
       * @var \Drupal\eca\Plugin\ECA\Event\EventInterface $event_plugin
       */
      $event_plugin = $this->eventPluginManager->createInstance($plugin_id);
    }
    catch (PluginException) {
      // @todo Log this exception.
      return NULL;
    }
    $event_class = $event_plugin->eventClass();
    $event = new $event_class(...$args);
    $this->eventDispatcher->dispatch($event, $event_plugin->eventName());
    return $event;
  }

}
