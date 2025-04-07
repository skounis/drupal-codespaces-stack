<?php

namespace Drupal\eca_form\Plugin\Action;

/**
 * Add a text field to a form.
 *
 * @Action(
 *   id = "eca_form_add_textfield",
 *   label = @Translation("Form: add text field"),
 *   description = @Translation("Add a plain text field, textarea or formatted text to the current form in scope."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddTextfield extends FormAddFieldActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'type' => 'textfield',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTypeOptions(): array {
    $type_options = [
      'textfield' => $this->t('Textfield'),
      'textarea' => $this->t('Textarea'),
    ];
    if ($this->moduleHandler->moduleExists('filter')) {
      $type_options['text_format'] = $this->t('Formatted text');
    }
    return $type_options;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if ($this->configuration['type'] === 'text_format') {
      $dependencies['module'][] = 'filter';
    }
    return $dependencies;
  }

}
