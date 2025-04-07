<?php

namespace Drupal\eca\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for entity-related events.
 */
interface EntityEventInterface {

  /**
   * Get the entity which was involved in the event.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity(): EntityInterface;

}
