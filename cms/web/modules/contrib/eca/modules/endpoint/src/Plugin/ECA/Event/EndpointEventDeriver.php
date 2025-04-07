<?php

namespace Drupal\eca_endpoint\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Base event plugins.
 */
class EndpointEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return EndpointEvent::definitions();
  }

}
