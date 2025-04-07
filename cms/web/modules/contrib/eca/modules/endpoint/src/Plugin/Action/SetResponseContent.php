<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Set the response content.
 *
 * @Action(
 *   id = "eca_endpoint_set_response_content",
 *   label = @Translation("Response: set content"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class SetResponseContent extends ResponseActionBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $content = (string) $this->tokenService->replaceClear($this->configuration['content']);
    $this->getResponse()->setContent($content);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'content' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#description' => $this->t('The response content to set.'),
      '#default_value' => $this->configuration['content'],
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
    $this->configuration['content'] = (string) $form_state->getValue('content');
    parent::submitConfigurationForm($form, $form_state);
  }

}
