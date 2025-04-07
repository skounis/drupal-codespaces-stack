<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Set weight on a field or form element.
 *
 * @Action(
 *   id = "eca_form_field_set_weight",
 *   label = @Translation("Form field: set weight"),
 *   description = @Translation("Defines weight on a form field or element."),
 *   eca_version_introduced = "2.1.0",
 *   type = "form"
 * )
 */
class FormFieldSetWeight extends FormFieldActionBase {

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
        $weight = trim((string) $this->tokenService->replaceClear($this->configuration['weight']));
        if (is_numeric($weight)) {
          $element['#weight'] = (int) $weight;
        }
        else {
          unset($element['#weight']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'weight' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['weight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('The weight as integer number.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['weight'] = $form_state->getValue('weight');
    parent::submitConfigurationForm($form, $form_state);
  }

}
