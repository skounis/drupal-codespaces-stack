<?php

namespace Drupal\eca_misc\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Routing event plugins.
 */
class RoutingEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return RoutingEvent::definitions();
  }

}
