<?php

namespace Drupal\eca_base\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;

/**
 * Plugin implementation of the ECA condition for key value store values.
 *
 * @EcaCondition(
 *   id = "eca_state",
 *   label = @Translation("Persistent state: compare"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class EcaState extends StringComparisonBase {

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    return $this->state->get($this->configuration['key'], '');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->configuration['value'];
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
      '#title' => $this->t('Key'),
      '#default_value' => $this->configuration['key'],
      '#weight' => -90,
      '#description' => $this->t('The key of the state.'),
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -70,
      '#description' => $this->t('The value of the state.'),
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
