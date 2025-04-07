<?php

namespace Drupal\eca_language\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Language event plugins.
 */
class LanguageEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return LanguageEvent::definitions();
  }

}
