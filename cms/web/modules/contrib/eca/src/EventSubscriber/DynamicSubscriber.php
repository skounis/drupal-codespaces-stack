<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Site\Settings;
use Drupal\eca\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A dynamic subscriber, listening on used events of active ECA configurations.
 */
class DynamicSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    foreach (\Drupal::state()->get('eca.subscribed', []) as $name => $prioritized) {
      $events[$name][] = ['onEvent', key($prioritized)];
    }
    return $events;
  }

  /**
   * Callback forwarding the given event to the ECA processor.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggered event that gets processed by the ECA processor.
   * @param string $event_name
   *   The specific event name that got triggered for that event.
   */
  public function onEvent(Event $event, string $event_name): void {
    try {
      if (!Settings::get('eca_disable', FALSE)) {
        Processor::get()->execute($event, $event_name);
      }
      // @phpstan-ignore-next-line
      elseif (\Drupal::currentUser()->hasPermission('administer eca')) {
        // @phpstan-ignore-next-line
        \Drupal::messenger()
          ->addWarning('ECA is disabled in your settings.php file.');
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // This is thrown during installation of eca and we can ignore this.
    }
  }

}
