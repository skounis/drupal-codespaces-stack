<?php

namespace Drupal\eca_project_browser\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Project Browser event plugins.
 */
class ProjectBrowserEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return ProjectBrowserEvent::definitions();
  }

}
