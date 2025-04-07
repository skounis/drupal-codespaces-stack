<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Insert content after to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_after",
 *   label = @Translation("Ajax Response: insert content after"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAfterCommand extends SetAjaxResponseInsertCommand {

  /**
   * {@inheritdoc}
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new AfterCommand($selector, $content, $settings);
  }

}
