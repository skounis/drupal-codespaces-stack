<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add an alert message to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_alert",
 *   label = @Translation("Ajax Response: set alert"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAlertCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $message = (string) $this->tokenService->replaceClear($this->configuration['message']);
    return new AlertCommand($message);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The alert message returned to the ajax response.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = (string) $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

}
