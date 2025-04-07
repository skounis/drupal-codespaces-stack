<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get a query parameter.
 *
 * @Action(
 *   id = "eca_endpoint_get_query_parameter",
 *   label = @Translation("Request: Get URL query parameter"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetQueryParameter extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): mixed {
    $query = $this->getRequest()->query->all();
    $name = trim((string) $this->tokenService->replaceClear($this->configuration['name']));
    if ($name === '') {
      return $query;
    }
    return $query[$name] ?? NULL;
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
      '#title' => $this->t('Query parameter name'),
      '#description' => $this->t('The name of the URL query parameter. Example: To get the parameter of the destination parameter <em>?destination=...</em>, then set <em>destination</em> as the parameter name. When this field is empty, then all parameters will be returned as a list, keyed by parameter name.'),
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
