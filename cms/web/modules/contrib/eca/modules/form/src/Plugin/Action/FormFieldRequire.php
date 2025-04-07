<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Set a form field as required.
 *
 * @Action(
 *   id = "eca_form_field_require",
 *   label = @Translation("Form field: set as required"),
 *   description = @Translation("Set a form field as required."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldRequire extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('required') : 'required';
  }

}
