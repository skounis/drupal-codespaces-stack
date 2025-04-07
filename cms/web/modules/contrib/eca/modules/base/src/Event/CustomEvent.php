<?php

namespace Drupal\eca_base\Event;

use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a custom event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_base\Event
 */
class CustomEvent extends Event implements TokenReceiverInterface {

  use TokenReceiverTrait;

  /**
   * The (optional) id for this event.
   *
   * @var string
   */
  protected string $eventId;

  /**
   * Additional arguments provided by the triggering context.
   *
   * @var array
   */
  protected array $arguments = [];

  /**
   * Provides a custom event.
   *
   * @param string $event_id
   *   The ID for this event, so that it only applies, if it matches the given
   *   event ID in the arguments. This could also be an empty string.
   * @param array $arguments
   *   (optional) Additional arguments provided by the triggering context.
   */
  public function __construct(string $event_id, array $arguments = []) {
    $this->eventId = $event_id;
    $this->arguments = $arguments;
  }

  /**
   * Returns the event ID.
   *
   * @return string
   *   The event ID.
   */
  public function getEventId(): string {
    return $this->eventId;
  }

}
