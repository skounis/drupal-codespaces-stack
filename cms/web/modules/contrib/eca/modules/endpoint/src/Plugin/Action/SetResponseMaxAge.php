<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;

/**
 * Set the response headers.
 *
 * @Action(
 *   id = "eca_endpoint_set_response_max_age",
 *   label = @Translation("Response: set max age"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class SetResponseMaxAge extends ResponseActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $max_age = (int) trim((string) $this->tokenService->replaceClear($this->configuration['max_age']));
    $s_max_age = (int) trim((string) $this->tokenService->replaceClear($this->configuration['s_max_age']));
    $response = $this->getResponse();
    $response->setMaxAge($max_age);
    $response->setSharedMaxAge($s_max_age);
    if ($this->configuration['set_expires']) {
      $expires = (string) ((new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp() + $max_age);
      $this->getResponse()->setExpires((new DrupalDateTime("@{$expires}"))->getPhpDateTime());
    }
    if ($this->configuration['set_public']) {
      $response->setPublic();
    }
    else {
      $response->setPrivate();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'max_age' => '',
      's_max_age' => '',
      'set_public' => TRUE,
      'set_expires' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['max_age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client value (max-age)'),
      '#description' => $this->t('The number of seconds for the client-side max age.'),
      '#default_value' => $this->configuration['max_age'],
      '#weight' => -50,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['s_max_age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shared value (s-max-age)'),
      '#description' => $this->t('The number of seconds for the shared max age.'),
      '#default_value' => $this->configuration['s_max_age'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['set_public'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set public'),
      '#description' => $this->t('Cacheability is usually only working when the response is set to be public. When not enabled, the response will be set as private.'),
      '#default_value' => $this->configuration['set_public'],
      '#weight' => -30,
      '#required' => FALSE,
    ];
    $form['set_expires'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set expires'),
      '#description' => $this->t('When enabled, the expires header is automatically derived from the defined max age.'),
      '#default_value' => $this->configuration['set_expires'],
      '#weight' => -20,
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['max_age'] = (string) $form_state->getValue('max_age');
    $this->configuration['s_max_age'] = (string) $form_state->getValue('s_max_age');
    $this->configuration['set_public'] = !empty($form_state->getValue('set_public'));
    $this->configuration['set_expires'] = !empty($form_state->getValue('set_expires'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
