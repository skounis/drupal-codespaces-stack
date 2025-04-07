<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Base class for form state property actions.
 */
abstract class FormStatePropertyActionBase extends FormActionBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'property_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of property'),
      '#description' => $this->t('Nested properties are supported by using dot notation. Example: <em>level1.level2</em>'),
      '#default_value' => $this->configuration['property_name'],
      '#weight' => -50,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['property_name'] = $form_state->getValue('property_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
