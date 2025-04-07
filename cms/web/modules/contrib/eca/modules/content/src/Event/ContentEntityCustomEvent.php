<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Event\TokenReceiverInterface;
use Drupal\eca\Event\TokenReceiverTrait;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Provides an entity aware custom event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_content\Event
 */
class ContentEntityCustomEvent extends ContentEntityBaseContentEntity implements TokenReceiverInterface {

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
   * Provides a custom event that is entity aware.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the custom event got triggered.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity service.
   * @param string $event_id
   *   The (optional) ID for this event, so that it only applies, if it matches
   *   the given event ID in the arguments.
   * @param array $arguments
   *   Additional arguments provided by the triggering context. This may at
   *   least contain the key "event_id" to filter custom events to apply only
   *   if that ID matches this ID. To trigger all custom events, the event ID
   *   should be omitted or left empty.
   */
  public function __construct(ContentEntityInterface $entity, ContentEntityTypes $entity_types, string $event_id, array $arguments) {
    parent::__construct($entity, $entity_types);
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
