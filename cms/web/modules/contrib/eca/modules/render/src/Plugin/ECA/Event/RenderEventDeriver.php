<?php

namespace Drupal\eca_render\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Base event plugins.
 */
class RenderEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return RenderEvent::definitions();
  }

}
