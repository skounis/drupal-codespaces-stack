<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Set a form field as disabled.
 *
 * @Action(
 *   id = "eca_form_field_disable",
 *   label = @Translation("Form field: set as disabled"),
 *   description = @Translation("Disable a form field."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldDisable extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup {
    return $human_readable ? $this->t('disabled') : 'disabled';
  }

  /**
   * {@inheritdoc}
   */
  protected function flagAllChildren(&$element, bool $flag): void {
    parent::flagAllChildren($element, $flag);
    $this->setFormFieldAttributes($element);
  }

  /**
   * Set form field attributes on the given element.
   *
   * Sometimes it is too late that the form builder sets proper HTML attributes.
   * Therefore, this helper method assures they are set.
   *
   * @param array &$element
   *   The form element.
   *
   * @see \Drupal\Core\Form\FormBuilder::handleInputElement
   */
  protected function setFormFieldAttributes(array &$element): void {
    if (empty($element['#input'])) {
      return;
    }
    if (!empty($element['#allow_focus'])) {
      $element['#attributes']['readonly'] = 'readonly';
    }
    else {
      $element['#attributes']['disabled'] = 'disabled';
    }
  }

}
