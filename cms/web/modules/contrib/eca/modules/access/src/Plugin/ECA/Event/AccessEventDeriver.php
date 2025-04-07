<?php

namespace Drupal\eca_access\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA access event plugins.
 */
class AccessEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return AccessEvent::definitions();
  }

}
