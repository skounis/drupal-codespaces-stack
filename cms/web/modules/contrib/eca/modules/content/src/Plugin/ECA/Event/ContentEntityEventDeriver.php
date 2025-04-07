<?php

namespace Drupal\eca_content\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Content Entity event plugins.
 */
class ContentEntityEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return ContentEntityEvent::definitions();
  }

}
