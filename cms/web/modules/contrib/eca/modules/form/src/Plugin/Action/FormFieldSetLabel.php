<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Set label on a field or form element.
 *
 * @Action(
 *   id = "eca_form_field_set_label",
 *   label = @Translation("Form field: set label"),
 *   description = @Translation("Defines label on a form field or element."),
 *   eca_version_introduced = "2.1.0",
 *   type = "form"
 * )
 */
class FormFieldSetLabel extends FormFieldActionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    if ($element = &$this->getTargetElement()) {
      $element = &$this->jumpToFirstFieldChild($element);
      if ($element) {
        $label = trim((string) $this->tokenService->replaceClear($this->configuration['label']));
        if ($label !== '') {
          $element['#title'] = $label;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'label' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element label'),
      '#description' => $this->t('The label of the form field or element.'),
      '#default_value' => $this->configuration['label'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['label'] = $form_state->getValue('label');
    parent::submitConfigurationForm($form, $form_state);
  }

}
