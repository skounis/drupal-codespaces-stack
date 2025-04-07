<?php

namespace Drupal\eca_form\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\EntityApplianceTrait;
use Drupal\eca\Event\FormEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Abstract base class for form events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_form\Event
 */
abstract class FormBase extends Event implements FormEventInterface {

  use EntityApplianceTrait;

  /**
   * The form array.
   *
   * This may be the complete form, or a sub-form, or a specific form element.
   *
   * @var array
   */
  protected array $form;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * Constructs a FormBase instance.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function __construct(array &$form, FormStateInterface $form_state) {
    $this->form = &$form;
    $this->formState = $form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function &getForm(): array {
    return $this->form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

}
