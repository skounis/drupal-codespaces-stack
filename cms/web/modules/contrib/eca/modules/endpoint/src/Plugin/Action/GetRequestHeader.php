<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get a request header.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_header",
 *   label = @Translation("Request: Get header"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetRequestHeader extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): mixed {
    $headers = $this->getRequest()->headers->all();
    $name = trim((string) $this->tokenService->replaceClear($this->configuration['name']));
    if ($name === '') {
      return $headers;
    }
    $header = $headers[$name] ?? NULL;
    if (is_array($header) && count($header) === 1) {
      $header = reset($header);
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header name'),
      '#description' => $this->t('The name / key of the request header. Example: <em>Content-Type</em>. When this field is empty, then all headers will be returned as a list, keyed by header name.'),
      '#default_value' => $this->configuration['name'],
      '#weight' => -20,
      '#required' => FALSE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['name'] = (string) $form_state->getValue('name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
