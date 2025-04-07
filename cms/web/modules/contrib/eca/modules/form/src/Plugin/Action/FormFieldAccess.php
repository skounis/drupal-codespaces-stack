<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Set access to a form field.
 *
 * @Action(
 *   id = "eca_form_field_access",
 *   label = @Translation("Form field: set access"),
 *   description = @Translation("Set access to a form field."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldAccess extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('access') : 'access';
  }

}
