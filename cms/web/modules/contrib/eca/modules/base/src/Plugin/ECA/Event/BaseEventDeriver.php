<?php

namespace Drupal\eca_base\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Base event plugins.
 */
class BaseEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return BaseEvent::definitions();
  }

}
