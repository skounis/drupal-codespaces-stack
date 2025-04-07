<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Base class for actions operating on option fields.
 */
abstract class FormFieldOptionsActionBase extends FormFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Directly call the parent of the parent, to save a bit of redundant
    // access check overhead.
    $result = FormActionBase::access($object, $account, TRUE);

    if ($result->isAllowed()) {
      $original_field_name = $this->configuration['field_name'];

      $missing_form_fields = [];
      foreach ($this->extractFormFieldNames($original_field_name) as $field_name) {
        $this->configuration['field_name'] = $field_name;
        if ($element = &$this->getTargetElement()) {
          $element = &$this->jumpToFirstFieldChild($element);
        }
        if (!$element || !isset($element['#options'])) {
          $missing_form_fields[] = $field_name;
        }
      }

      if ($missing_form_fields) {
        $form_field_result = AccessResult::forbidden(sprintf("The following form fields were not found, or they are no valid option fields: %s", implode(', ', $missing_form_fields)));
      }
      else {
        $form_field_result = AccessResult::allowed();
      }

      $result = $result->andIf($form_field_result);

      // Restoring the original config entry.
      $this->configuration['field_name'] = $original_field_name;
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
