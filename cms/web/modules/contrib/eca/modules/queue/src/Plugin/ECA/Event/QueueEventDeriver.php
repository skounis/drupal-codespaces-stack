<?php

namespace Drupal\eca_queue\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Queue event plugins.
 */
class QueueEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return QueueEvent::definitions();
  }

}
