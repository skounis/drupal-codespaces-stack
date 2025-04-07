<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Insert content before by the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_before",
 *   label = @Translation("Ajax Response: insert before content"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseBeforeCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new BeforeCommand($selector, $content, $settings);
  }

}
