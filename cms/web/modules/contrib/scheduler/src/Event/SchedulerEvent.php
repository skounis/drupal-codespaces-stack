<?php

namespace Drupal\scheduler\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Wraps a scheduler event for event listeners.
 */
class SchedulerEvent extends EventBase {

  /**
   * Gets the entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity object that caused the event to fire.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Sets the entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object that caused the event to fire.
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Gets the node object (same as the entity object).
   *
   * This method is retained for backwards compatibility because implementations
   * of the event subscriber functions may be using $event->getNode().
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity object that caused the event to fire.
   */
  public function getNode() {
    return $this->entity;
  }

  /**
   * Sets the node object (same as the entity object).
   *
   * This method is retained for backwards compatibility because implementations
   * of the event subscriber functions may be using $event->setNode().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object that caused the event to fire.
   */
  public function setNode(EntityInterface $entity) {
    $this->entity = $entity;
  }

}
