<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Add a hidden field to a form.
 *
 * @Action(
 *   id = "eca_form_add_hiddenfield",
 *   label = @Translation("Form: add hidden field"),
 *   description = @Translation("Add a hidden input field to the current form in scope."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddHiddenField extends FormAddFieldActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'type' => 'hidden',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTypeOptions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFieldElement(): array {
    $field_element = [
      '#type' => $this->configuration['type'],
      '#value' => $this->tokenService->replaceClear($this->configuration['value']),
      '#weight' => (int) $this->configuration['weight'],
    ];
    return $field_element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value of the hidden field.'),
      '#default_value' => $this->configuration['value'],
      '#eca_token_replacement' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['title'], $form['description'], $form['required'], $form['default_value']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['value'] = $form_state->getValue('value');
    $config = &$this->configuration;
    unset($config['title'], $config['description'], $config['required'], $config['default_value']);
  }

}
