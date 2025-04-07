<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\OpenModalDialogCommand;

/**
 * Add open modal dialog command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_open_modal_dialog",
 *   label = @Translation("Ajax Response: open modal dialog"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseOpenModalDialogCommand extends SetAjaxResponseOpenDialogCommand {

  /**
   * {@inheritdoc}
   */
  protected function getDialogCommand(string $selector, string $title, string|array $content, array $options, ?array $settings): CommandInterface {
    return new OpenModalDialogCommand($title, $content, $options, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    unset($config['selector']);
    return $config;
  }

}
