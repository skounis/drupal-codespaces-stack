<?php

namespace Drupal\eca_log\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Log event plugins.
 */
class LogEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return LogEvent::definitions();
  }

}
