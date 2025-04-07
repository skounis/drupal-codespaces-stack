<?php

namespace Drupal\eca\Event;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for form API related events.
 */
interface FormEventInterface {

  /**
   * Get the form array.
   *
   * This may be the complete form, or a sub-form, or a specific form element.
   *
   * @return array|null
   *   The form array as reference or NULL if there is no form array.
   */
  public function &getForm(): ?array;

  /**
   * Gets the form state object which was involved in the form event.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function getFormState(): FormStateInterface;

}
