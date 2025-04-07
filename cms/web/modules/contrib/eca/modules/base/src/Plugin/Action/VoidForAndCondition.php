<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\eca\Plugin\Action\ActionBase;

/**
 * Empty action to chain conditions with AND constraint.
 *
 * @Action(
 *   id = "eca_void_and_condition",
 *   label = @Translation("Chain action for AND condition"),
 *   description = @Translation("This action chains other actions with an explicit AND condition."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class VoidForAndCondition extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {}

}
