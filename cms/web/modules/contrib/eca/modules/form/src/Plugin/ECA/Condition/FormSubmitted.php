<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Checks whether the current form is submitted.
 *
 * @EcaCondition(
 *   id = "eca_form_submitted",
 *   label = @Translation("Form: is submitted"),
 *   description = @Translation("Checks whether the current form is submitted."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class FormSubmitted extends ConditionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (!($form_state = $this->getCurrentFormState())) {
      return FALSE;
    }
    return $this->negationCheck($form_state->isSubmitted());
  }

}
