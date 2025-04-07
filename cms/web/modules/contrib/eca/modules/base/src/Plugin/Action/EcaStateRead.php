<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Action to read value from ECA's key value store and store it as token.
 *
 * @Action(
 *   id = "eca_state_read",
 *   label = @Translation("Persistent state: read"),
 *   description = @Translation("Reads a value from the Drupal state by the given key. The result is stored in a token."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class EcaStateRead extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $key = $this->tokenService->replace($this->configuration['key']);
    $result = AccessResult::allowedIf(is_string($key) && $key !== '');
    if (!$result->isAllowed()) {
      $result->setReason('The given key is invalid.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $key = $this->tokenService->replace($this->configuration['key']);
    $value = $this->state->get($key, '');
    $this->tokenService->addTokenData($this->configuration['token_name'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'key' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('State key'),
      '#default_value' => $this->configuration['key'],
      '#weight' => -20,
      '#description' => $this->t('The key of the Drupal state.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#description' => $this->t('The name of the token, the value is stored into.'),
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
