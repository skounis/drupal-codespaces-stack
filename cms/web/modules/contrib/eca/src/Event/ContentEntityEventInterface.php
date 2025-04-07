<?php

namespace Drupal\eca\Event;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for content-entity-related events.
 */
interface ContentEntityEventInterface extends EntityEventInterface {

  /**
   * Get the content entity which was involved in the event.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface;

}
