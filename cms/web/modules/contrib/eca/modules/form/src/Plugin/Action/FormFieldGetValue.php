<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormFieldPluginTrait;

/**
 * Get the submitted input of a form field.
 *
 * @Action(
 *   id = "eca_form_field_get_value",
 *   label = @Translation("Form field: get submitted value"),
 *   description = @Translation("Get the submitted input of a form field and store it as a token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldGetValue extends ConfigurableActionBase {

  use FormFieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + $this->defaultFormFieldConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The field value will be loaded into this specified token.'),
      '#required' => TRUE,
      '#weight' => -45,
      '#eca_token_reference' => TRUE,
    ];
    $form = $this->buildFormFieldConfigurationForm($form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateFormFieldConfigurationForm($form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->submitFormFieldConfigurationForm($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!$this->getCurrentFormState()) {
      return;
    }

    $original_field_name = $this->configuration['field_name'];
    $this->configuration['field_name'] = (string) $this->tokenService->replace($original_field_name);

    $value = $this->getSubmittedValue();
    $this->filterFormFieldValue($value);
    $this->tokenService->addTokenData($this->configuration['token_name'], $value);

    // Restoring the original config entry.
    $this->configuration['field_name'] = $original_field_name;
  }

}
