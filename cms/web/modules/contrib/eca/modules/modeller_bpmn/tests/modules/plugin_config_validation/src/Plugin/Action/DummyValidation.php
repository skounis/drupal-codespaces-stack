<?php

namespace Drupal\eca_test_model_plugin_config_validation\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Dummy action to test config validation.
 *
 * @Action(
 *   id = "eca_test_model_plugin_config_validation",
 *   label = @Translation("Test: Dummy action to validate configuration"),
 *   nodocs = true
 * )
 */
class DummyValidation extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    // Nothing to do!
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'dummy' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['dummy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dummy'),
      '#default_value' => $this->configuration['dummy'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    $dummy = $form_state->getValue('dummy');
    if ($dummy === 'wrong') {
      $form_state->setErrorByName('dummy', 'This value is not allowed.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['dummy'] = $form_state->getValue('dummy');
    parent::submitConfigurationForm($form, $form_state);
  }

}
