<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add the close dialog command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_close_dialog",
 *   label = @Translation("Ajax Response: close dialog"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseCloseDialogCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    if ($selector === '') {
      $selector = NULL;
    }
    $persist = (bool) $this->configuration['persist'];
    return new CloseDialogCommand($selector, $persist);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'persist' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (isset($this->configuration['selector'])) {
      $form['selector'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSS Selector'),
        '#description' => $this->t('CSS selector string of the dialog to close; leave empty for the default.'),
        '#default_value' => $this->configuration['selector'],
        '#weight' => -45,
        '#required' => TRUE,
        '#eca_token_replacement' => TRUE,
      ];
    }
    $form['persist'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Persist dialog in DOM'),
      '#description' => $this->t('Whether to persist the dialog in the DOM or not.'),
      '#default_value' => $this->configuration['persist'],
      '#weight' => -40,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (isset($this->defaultConfiguration()['selector'])) {
      $this->configuration['selector'] = (string) $form_state->getValue('selector');
    }
    $this->configuration['persist'] = (bool) $form_state->getValue('persist');
    parent::submitConfigurationForm($form, $form_state);
  }

}
