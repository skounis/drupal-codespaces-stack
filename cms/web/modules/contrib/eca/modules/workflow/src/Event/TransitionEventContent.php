<?php

namespace Drupal\eca_workflow\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Event\ContentEntityEventInterface;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a moderation state changed.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class TransitionEventContent extends Event implements ContentEntityEventInterface {

  /**
   * The moderated entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * From state (if given).
   *
   * @var string|null
   */
  protected ?string $fromState;

  /**
   * To state (if given).
   *
   * @var string
   */
  protected string $toState;

  /**
   * The entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * Constructs a new TransitionEvent object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The moderated entity.
   * @param string|null $from_state
   *   (optional) From state.
   * @param string $to_state
   *   New state.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity types service.
   */
  public function __construct(ContentEntityInterface $entity, ?string $from_state, string $to_state, ContentEntityTypes $entity_types) {
    $this->entity = $entity;
    $this->fromState = $from_state;
    $this->toState = $to_state;
    $this->entityTypes = $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns the from state or NULL, if not available.
   *
   * @return string|null
   *   The from state if available, NULL otherwise.
   */
  public function getFromState(): ?string {
    return $this->fromState;
  }

  /**
   * Returns the destination state.
   *
   * @return string
   *   The destination state.
   */
  public function getToState(): string {
    return $this->toState;
  }

}
