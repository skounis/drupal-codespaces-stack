<?php

namespace Drupal\eca\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\eca\Event\FormEventInterface;
use Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber;

/**
 * Trait of ECA plugins making use of the current form.
 *
 * @todo Consider using a static stack instead of using the source event.
 */
trait FormPluginTrait {

  /**
   * Get the currently targeted form array.
   *
   * @return array|null
   *   The form array as reference, or NULL if there is none.
   */
  protected function &getCurrentForm(): ?array {
    if (!($event = $this->getCurrentFormEvent())) {
      $nothing = NULL;
      return $nothing;
    }

    return $event->getForm();
  }

  /**
   * Get the currently targeted form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface|null
   *   The form state, or NULL if there is none.
   */
  protected function getCurrentFormState(): ?FormStateInterface {
    if (!($event = $this->getCurrentFormEvent())) {
      return NULL;
    }

    return $event->getFormState();
  }

  /**
   * Get the currently involved form event.
   *
   * @return \Drupal\eca\Event\FormEventInterface|null
   *   The involved form event, or NULL if there is none.
   */
  protected function getCurrentFormEvent(): ?FormEventInterface {
    if (isset($this->event) && ($this->event instanceof FormEventInterface)) {
      return $this->event;
    }

    if ($events = EcaExecutionFormSubscriber::get()->getStackedFormEvents()) {
      return reset($events);
    }

    return NULL;
  }

  /**
   * Gracefully inserts a form element without losing child elements.
   *
   * @param array &$form
   *   The form array where to insert the element.
   * @param array &$name
   *   The name that identifies the element in the form.
   * @param array &$element
   *   The form element to insert for $name.
   */
  protected function insertFormElement(array &$form, array &$name, array &$element): void {
    $exists = FALSE;
    $existing_element = &NestedArray::getValue($form, $name, $exists);
    $children = [];
    if ($exists && is_array($existing_element)) {
      foreach (Element::children($existing_element) as $key) {
        $children[$key] = $existing_element[$key];
      }
    }
    NestedArray::setValue($form, $name, $element + $children, TRUE);
  }

}
