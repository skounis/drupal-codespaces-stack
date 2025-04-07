<?php

namespace Drupal\eca_migrate\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Migrate event plugins.
 */
class MigrateEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return MigrateEvent::definitions();
  }

}
