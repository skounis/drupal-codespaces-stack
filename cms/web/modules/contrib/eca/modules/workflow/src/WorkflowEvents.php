<?php

namespace Drupal\eca_workflow;

/**
 * Defines events provided by the ECA Workflow module.
 */
final class WorkflowEvents {

  /**
   * Dispatches when a moderation state changed.
   *
   * @Event
   *
   * @var string
   */
  public const TRANSITION = 'eca_workflow.transition';

}
