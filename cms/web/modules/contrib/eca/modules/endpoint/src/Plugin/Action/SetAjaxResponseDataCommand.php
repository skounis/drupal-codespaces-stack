<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\DataCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a data command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_data",
 *   label = @Translation("Ajax Response: set data attribute"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseDataCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    $name = (string) $this->tokenService->replaceClear($this->configuration['name']);
    $value = (string) $this->tokenService->replaceClear($this->configuration['value']);
    return new DataCommand($selector, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'name' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#description' => $this->t('A CSS selector for the elements to which the data will be attached.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The key of the data to be attached to elements matched by the selector.'),
      '#default_value' => $this->configuration['name'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value of the data to be attached to elements matched by the selector.'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -35,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['name'] = (string) $form_state->getValue('name');
    $this->configuration['value'] = (string) $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
