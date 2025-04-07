<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Add the close modal dialog command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_close_modal_dialog",
 *   label = @Translation("Ajax Response: close modal dialog"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseCloseModalDialogCommand extends SetAjaxResponseCloseDialogCommand {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $persist = (bool) $this->configuration['persist'];
    return new CloseModalDialogCommand($persist);
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
