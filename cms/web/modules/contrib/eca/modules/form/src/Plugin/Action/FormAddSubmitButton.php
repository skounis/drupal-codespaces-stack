<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Plugin\FormFieldPluginTrait;
use Drupal\eca_form\HookHandler;

/**
 * Add a submit button to a form.
 *
 * @Action(
 *   id = "eca_form_add_submit_button",
 *   label = @Translation("Form: add submit button"),
 *   description = @Translation("Add a submit button with a type and a label to a form."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddSubmitButton extends FormActionBase {

  use FormFieldPluginTrait;
  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form = &$this->getCurrentForm())) {
      return;
    }
    $name = trim((string) $this->tokenService->replace($this->configuration['name']));
    if ($name === '') {
      throw new \InvalidArgumentException('Cannot use an empty string as trigger name');
    }
    $this->configuration['field_name'] = $name;
    $name = $this->getFieldNameAsArray();

    $button_element = [
      '#type' => 'submit',
      '#name' => 'op',
      '#value' => $this->tokenService->replaceClear($this->configuration['value']),
      '#weight' => (int) $this->configuration['weight'],
      '#access' => TRUE,
      '#submit' => [[HookHandler::class, 'submit']],
    ];
    if (count($name) > 1) {
      $button_element['#parents'] = $name;
    }
    $button_type = $this->configuration['button_type'];
    if ($button_type === '_eca_token') {
      $button_type = $this->getTokenValue('button_type', '_none');
    }
    if (!empty($button_type) && ($button_type !== '_none')) {
      $button_element['#button_type'] = $this->configuration['button_type'];
    }

    if (isset($form['actions'])) {
      $element = &$form['actions'];
    }
    else {
      $element = &$form;
    }

    NestedArray::setValue($element, $name, $button_element, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'value' => '',
      'weight' => '0',
      'button_type' => '_none',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trigger name'),
      '#description' => $this->t('The trigger name must be a machine name and is used for being identified on form submission. Example: <em>accept</em>, <em>send</em>. It can later be accessed via token <em>[current_form:triggered]</em>.'),
      '#weight' => -10,
      '#default_value' => $this->configuration['name'],
      '#required' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#description' => $this->t('The label of the button shown to the user.'),
      '#weight' => -9,
      '#default_value' => $this->configuration['value'],
      '#required' => TRUE,
    ];
    $form['button_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Button type'),
      '#options' => [
        '_none' => $this->t('- No specific type -'),
        'primary' => $this->t('Primary'),
        'secondary' => $this->t('Secondary'),
        'danger' => $this->t('Danger'),
      ],
      '#default_value' => $this->configuration['button_type'],
      '#description' => $this->t('Here you can select the type of the button from the list.'),
      '#weight' => -8,
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('The lower the weight, the submit action appears before other submit actions having a higher weight.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -7,
      '#required' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['weight'] = $form_state->getValue('weight');
    $this->configuration['button_type'] = $form_state->getValue('button_type');
  }

}
