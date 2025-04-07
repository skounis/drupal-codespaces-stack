<?php

namespace Drupal\eca_test_array\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Base event plugins.
 */
class ArrayEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return ArrayEvent::definitions();
  }

}
