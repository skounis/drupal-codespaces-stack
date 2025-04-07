<?php

namespace Drupal\eca_config\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Config event plugins.
 */
class ConfigEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return ConfigEvent::definitions();
  }

}
