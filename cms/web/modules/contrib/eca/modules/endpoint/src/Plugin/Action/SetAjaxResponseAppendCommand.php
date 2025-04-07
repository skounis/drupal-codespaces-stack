<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Append content to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_append",
 *   label = @Translation("Ajax Response: append content"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAppendCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new AppendCommand($selector, $content, $settings);
  }

}
