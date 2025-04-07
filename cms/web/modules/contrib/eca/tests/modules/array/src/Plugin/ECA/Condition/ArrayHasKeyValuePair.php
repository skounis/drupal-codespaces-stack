<?php

namespace Drupal\eca_test_array\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;

/**
 * Evaluates whether a certain key-value pair exists in a static array.
 *
 * @EcaCondition(
 *   id = "eca_test_array_has_key_and_value",
 *   label = @Translation("Static array: has key-value pair")
 * )
 */
class ArrayHasKeyValuePair extends StringComparisonBase {

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    if (!isset(ArrayWrite::$array[$this->configuration['key']])) {
      return '_ARRAY_KEY_IS_NOT_SET_';
    }
    return ArrayWrite::$array[$this->configuration['key']];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->tokenService->replaceClear($this->configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'key' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['key'],
      '#title' => $this->t('Key'),
      '#weight' => 10,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['value'],
      '#title' => $this->t('Value'),
      '#weight' => 20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
