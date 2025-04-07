<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Add a JavaScript state to a field or form element.
 *
 * @Action(
 *   id = "eca_form_field_add_state",
 *   label = @Translation("Form field: add state"),
 *   description = @Translation("Add JavaScript state to a form field or element."),
 *   eca_version_introduced = "2.1.0",
 *   type = "form"
 * )
 */
class FormFieldAddState extends FormFieldActionBase {

  use PluginFormTrait;

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
        $selector = trim((string) $this->tokenService->replace($this->configuration['selector']));
        $state = $this->configuration['state'];
        if ($state === '_eca_token') {
          $state = $this->getTokenValue('state', 'enabled');
        }
        $condition = $this->configuration['condition'];
        if ($condition === '_eca_token') {
          $condition = $this->getTokenValue('condition', 'empty');
        }
        $value = ($condition === 'value') ?
          trim((string) $this->tokenService->replaceClear($this->configuration['value'])) :
          TRUE;
        if ($selector !== '') {
          $element['#states'][$state][$selector][] = [
            $condition => $value,
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'state' => '',
      'condition' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#description' => $this->t('The JQuery selector for the remote element controlling the state of this field.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    $form['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#description' => $this->t('The state of this field that should be controlled by the remote element.'),
      '#options' => [
        'enabled' => $this->t('enabled'),
        'disabled' => $this->t('disabled'),
        'required' => $this->t('required'),
        'optional' => $this->t('optional'),
        'visible' => $this->t('visible'),
        'invisible' => $this->t('invisible'),
        'checked' => $this->t('checked'),
        'unchecked' => $this->t('unchecked'),
        'expanded' => $this->t('expanded'),
        'collapsed' => $this->t('collapsed'),
      ],
      '#default_value' => $this->configuration['state'],
      '#required' => TRUE,
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['condition'] = [
      '#type' => 'select',
      '#title' => $this->t('Condition'),
      '#description' => $this->t('The condition of the remote element controlling the state of this field.'),
      '#options' => [
        'empty' => $this->t('empty'),
        'filled' => $this->t('filled'),
        'checked' => $this->t('checked'),
        'unchecked' => $this->t('unchecked'),
        'expanded' => $this->t('expanded'),
        'collapsed' => $this->t('collapsed'),
        'value' => $this->t('value'),
      ],
      '#default_value' => $this->configuration['condition'],
      '#required' => TRUE,
      '#weight' => -15,
      '#eca_token_select_option' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value for the condition. This is only required if "value" is selected as the condition.'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = $form_state->getValue('selector');
    $this->configuration['state'] = $form_state->getValue('state');
    $this->configuration['condition'] = $form_state->getValue('condition');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
