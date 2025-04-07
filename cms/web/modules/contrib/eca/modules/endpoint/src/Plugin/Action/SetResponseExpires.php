<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;

/**
 * Set the response expires.
 *
 * @Action(
 *   id = "eca_endpoint_set_response_expires",
 *   label = @Translation("Response: set expires"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class SetResponseExpires extends ResponseActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $expires = $this->tokenService->replaceClear($this->configuration['expires']);
    $expires = ctype_digit((string) $expires) ? new DrupalDateTime("@$expires") : new DrupalDateTime($expires, new \DateTimeZone('UTC'));
    $this->getResponse()->setExpires($expires->getPhpDateTime());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'expires' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['expires'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expires'),
      '#description' => $this->t('The date of expiry, either formatted as a date time string, or a UNIX timestamp.'),
      '#default_value' => $this->configuration['expires'],
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
    $this->configuration['expires'] = (string) $form_state->getValue('expires');
    parent::submitConfigurationForm($form, $form_state);
  }

}
