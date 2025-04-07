<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Set the response status code.
 *
 * @Action(
 *   id = "eca_endpoint_set_response_status_code",
 *   label = @Translation("Response: set status code"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class SetResponseStatusCode extends ResponseActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $code = (int) trim((string) $this->tokenService->replaceClear($this->configuration['code']));
    $this->getResponse()->setStatusCode($code);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'code' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Status code'),
      '#description' => $this->t('Must be a valid HTTP status code.'),
      '#default_value' => $this->configuration['code'],
      '#weight' => -20,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['code'] = (string) $form_state->getValue('code');
    parent::submitConfigurationForm($form, $form_state);
  }

}
