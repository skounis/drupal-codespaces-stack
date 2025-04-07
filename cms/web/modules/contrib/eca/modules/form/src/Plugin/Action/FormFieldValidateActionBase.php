<?php

namespace Drupal\eca_form\Plugin\Action;

/**
 * Base class for form field validation actions.
 */
abstract class FormFieldValidateActionBase extends FormFieldActionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * Set a form error to the configured field.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $message
   *   The error message.
   */
  protected function setError($message): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    if ($target_element = &$this->getTargetElement()) {
      if (isset($target_element['#parents'])) {
        $form_state->setErrorByName(implode('][', $target_element['#parents']), $message);
        return;
      }
    }
    if (($name = trim((string) $this->configuration['field_name'])) !== '') {
      // Convert the field name to the bracket syntax as required by
      // FormStateInterface::setErrorByName().
      $name = str_replace(']', '', $name);
      $name = str_replace('[', '][', $name);

      $form_state->setErrorByName($name, $message);
    }
    elseif ($form = &$this->getCurrentForm()) {
      $form_state->setError($form, $message);
    }
  }

}
