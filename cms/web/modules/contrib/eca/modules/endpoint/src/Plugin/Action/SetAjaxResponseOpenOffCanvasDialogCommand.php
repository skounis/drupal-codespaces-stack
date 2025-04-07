<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Add open off-canvas dialog command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_open_off_canvas_dialog",
 *   label = @Translation("Ajax Response: open off-canvas dialog"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseOpenOffCanvasDialogCommand extends SetAjaxResponseOpenModalDialogCommand {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  protected function getDialogCommand(string $selector, string $title, string|array $content, array $options, ?array $settings): CommandInterface {
    $position = $this->configuration['position'];
    if ($position === '_eca_token') {
      $position = $this->getTokenValue('position', 'side');
    }
    return new OpenOffCanvasDialogCommand($title, $content, $options, $settings, $position);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'position' => 'side',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#description' => $this->t('The position to render the off-canvas dialog.'),
      '#options' => [
        'side' => $this->t('Side'),
        'top' => $this->t('Top'),
      ],
      '#default_value' => $this->configuration['position'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['position'] = (string) $form_state->getValue('position');
    parent::submitConfigurationForm($form, $form_state);
  }

}
