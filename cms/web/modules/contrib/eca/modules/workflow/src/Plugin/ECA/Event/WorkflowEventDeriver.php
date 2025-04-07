<?php

namespace Drupal\eca_workflow\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Workflow event plugins.
 */
class WorkflowEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return WorkflowEvent::definitions();
  }

}
