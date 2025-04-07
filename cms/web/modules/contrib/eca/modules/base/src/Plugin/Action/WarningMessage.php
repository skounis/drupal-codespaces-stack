<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\MessageAction;

/**
 * Sends a warning message to the current user's screen.
 *
 * @Action(
 *   id = "eca_warning_message",
 *   label = @Translation("Display a warning message to the user"),
 *   eca_version_introduced = "1.1.0",
 *   type = "system"
 * )
 */
class WarningMessage extends MessageAction {

  /**
   * {@inheritdoc}
   *
   * Mainly copied from parent execute method, except for a different messenger
   * instruction.
   */
  public function execute(mixed $entity = NULL): void {
    if (empty($this->configuration['node'])) {
      $this->configuration['node'] = $entity;
    }
    $message = $this->token->replace($this->configuration['message'], $this->configuration);
    $build = [
      '#markup' => $message,
    ];

    // @todo Fix in https://www.drupal.org/node/2577827
    $this->messenger->addWarning($this->renderer->renderInIsolation($build));
  }

}
