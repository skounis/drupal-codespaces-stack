<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\PrependCommand;

/**
 * Prepend content by the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_prepend",
 *   label = @Translation("Ajax Response: prepend content"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponsePrependCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new PrependCommand($selector, $content, $settings);
  }

}
