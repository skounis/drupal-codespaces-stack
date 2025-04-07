<?php

namespace Drupal\eca_workflow\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Deriver for ECA Workflow action plugins.
 */
class WorkflowTransitionDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    /** @var \Drupal\workflows\WorkflowInterface[] $workflows */
    $workflows = Workflow::loadMultiple();
    $this->derivatives = [];

    foreach ($workflows as $workflow_id => $workflow) {
      $this->derivatives[$workflow_id] = [
        'workflow_id' => $workflow->id(),
        'label' => 'Entity workflow ' . $workflow->label() . ': transition',
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
