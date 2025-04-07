<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Replace content by the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_replace",
 *   label = @Translation("Ajax Response: replace content"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseReplaceCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new ReplaceCommand($selector, $content, $settings);
  }

}
