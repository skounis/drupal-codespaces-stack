<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get the default value of a form field.
 *
 * @Action(
 *   id = "eca_form_field_get_default_value",
 *   label = @Translation("Form field: get default value"),
 *   description = @Translation("Get the default value of a form field and store it as a token."),
 *   eca_version_introduced = "2.0.0",
 *   type = "form"
 * )
 */
class FormFieldGetDefaultValue extends FormFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  protected bool $supportsMultiple = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $element = &$this->getTargetElement();
    $this->tokenService->addTokenData(
      $this->configuration['token_name'],
      $element['#default_value'] ?? NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a token where the value should be stored.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
