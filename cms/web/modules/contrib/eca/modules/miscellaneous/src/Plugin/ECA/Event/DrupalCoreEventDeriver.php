<?php

namespace Drupal\eca_misc\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Drupal Core event plugins.
 */
class DrupalCoreEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return DrupalCoreEvent::definitions();
  }

}
