<?php

namespace Drupal\eca_views\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Views event plugins.
 */
class ViewsEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return ViewsEvent::definitions();
  }

}
