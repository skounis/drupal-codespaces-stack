<?php

namespace Drupal\eca_form;

use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\eca\Event\BaseHookHandler;

/**
 * The handler for hook implementations within the eca_form.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * Get the hook handler as service instance.
   *
   * @return \Drupal\eca_form\HookHandler
   *   The hook handler as service instance.
   */
  public static function get(): HookHandler {
    return \Drupal::service('eca_form.hook_handler');
  }

  /**
   * Triggers the event to alter a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['#form_id']) && ($form['#form_id'] === 'system_modules_uninstall_confirm_form')) {
      // When this module is being uninstalled via UI, it will lead to a fatal.
      // To avoid this, the module uninstall confirm form is not supported.
      // @see https://www.drupal.org/project/eca/issues/3305797
      return;
    }
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }
    $this->triggerEvent->dispatchFromPlugin('form:form_build', $form, $form_state);
    // Add the handlers on class-level, to avoid expensive and possibly faulty
    // serialization of nested object references during form submissions.
    $form['#process'][] = [static::class, 'process'];
    $form['#after_build'][] = [static::class, 'afterBuild'];
    $form['#validate'][] = [static::class, 'validate'];
    $form['#submit'][] = [static::class, 'submit'];
    $this->addSubmitHandler($form);
  }

  /**
   * Add submit handler to nested elements if necessary.
   *
   * Walks through the element array recursively and adds the extra
   * submit-handler to all elements where necessary.
   *
   * @param array $elements
   *   A render array to walk through.
   */
  protected function addSubmitHandler(array &$elements): void {
    foreach (Element::children($elements) as $key) {
      if (is_array($elements[$key])) {
        // Only add our submit handler, when at least one other submit handler
        // is present for the element. The form submitter service calls
        // form-level submit handlers when no submit handler is specified, i.e.
        // either no #submit array is given at all, or the given array is empty.
        // @see \Drupal\Core\Form\FormSubmitter::executeSubmitHandlers()
        if (!empty($elements[$key]['#submit'])) {
          $submit_handler = [static::class, 'submit'];
          // Make sure our submit handler is added only once.
          if (!in_array($submit_handler, $elements[$key]['#submit'], TRUE)) {
            $elements[$key]['#submit'][] = $submit_handler;
          }
        }
        $this->addSubmitHandler($elements[$key]);
      }
    }
  }

  /**
   * Triggers the event to process a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function process(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::get()->triggerEvent->dispatchFromPlugin('form:form_process', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event after form building was completed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::get()->triggerEvent->dispatchFromPlugin('form:form_after_build', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event to validate a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validate(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::get()->triggerEvent->dispatchFromPlugin('form:form_validate', $form, $form_state);
    }
  }

  /**
   * Triggers the event to submit a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submit(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::get()->triggerEvent->dispatchFromPlugin('form:form_submit', $form, $form_state);
    }
  }

  /**
   * Triggers the event to alter an inline entity form.
   *
   * @param array &$entity_form
   *   The render array of the inline entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent form state.
   */
  public function alterInlineEntityForm(array &$entity_form, FormStateInterface $form_state): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }
    if (!isset($entity_form['#eca_ief_info'])) {
      return;
    }
    $info = &$entity_form['#eca_ief_info'];
    $this->triggerEvent->dispatchFromPlugin('form:ief_build', $entity_form, $form_state, $entity_form['#entity'], $info['parent'], $info['field_name'], $info['delta'], $info['widget_plugin_id']);
    // Pass along the info to the after build callback, but only parent UUID
    // is needed there.
    $info['parent'] = $info['parent']->uuid();
    $entity_form['#after_build'][] = [
      static::class,
      'inlineEntityFormAfterBuild',
    ];
  }

  /**
   * Manually builds the entity of the inline form using most recent form input.
   *
   * Only needed when coming from the inline_entity_form module, because the
   * embedded entity does not automatically hold most recent form input.
   *
   * @param array $form
   *   The inline entity form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   *
   * @return array
   *   The inline entity form array.
   *
   * @see eca_form_field_widget_single_element_inline_entity_form_complex_form_alter()
   */
  public static function inlineEntityFormAfterBuild($form, FormStateInterface $form_state): array {
    if (!\Drupal::request()->request->get(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER) || $form_state->isRebuilding() || ($form_state->has('skip_eca'))) {
      // No need to do entity building when Ajax is not involved.
      return $form;
    }

    $entity = $form['#entity'];

    try {
      $form['#entity'] = clone $entity;

      // Disabled the processing of this part until we've found a solution for
      // IEF complex widgets.
      // @see https://www.drupal.org/project/eca/issues/3469697
      if ($form['#eca_ief_info']['widget_plugin_id'] !== 'inline_entity_form_complex') {
        $original_triggering_element = $form_state->getTriggeringElement();
        $form_state->setTriggeringElement([
          '#limit_validation_errors' => [],
        ]);
        /** @var \Drupal\Core\Form\FormValidatorInterface $validator */
        $validator = \Drupal::service('form_validator');
        $form_state->setValidationEnforced(TRUE);
        $validator->validateForm(\Drupal::formBuilder()->getFormId($form_state->getFormObject(), $form_state), $form, $form_state);
        $form_state->setTriggeringElement($original_triggering_element);
        $form_state->setValidationEnforced(TRUE);
      }

      /** @var \Drupal\inline_entity_form\InlineFormInterface $handler */
      $handler = \Drupal::entityTypeManager()->getHandler($form['#entity']->getEntityTypeId(), 'inline_form');
      $handler->entityFormSubmit($form, $form_state);

      $info = $form['#eca_ief_info'];
      $form_state->set([
        'eca_ief',
        $info['parent'],
        $info['field_name'],
        $info['delta'],
      ], $form['#entity']);
    }
    catch (\Throwable $t) {
      \Drupal::logger('eca_form')->warning("An error occurred while trying to build an inline entity from submitted form values. This might be a problem in case you use ECA to extend inline entity forms. Please report this to the ECA issue queue to help us improving it.");
    }

    // Info is not needed anymore, remove it to prevent unnecessary bloat.
    unset($form['#eca_ief_info']);
    // Restore the original object.
    $form['#entity'] = $entity;

    return $form;
  }

  /**
   * Alters the "inline_entity_form_complex" widget.
   *
   * @param mixed &$element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed &$context
   *   The widget context.
   */
  public function fieldWidgetSingleElementInlineEntityFormComplexFormAlter(&$element, FormStateInterface $form_state, &$context): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }

    // Pass along information for ::alterInlineEntityForm().
    if (isset($element['inline_entity_form'])) {
      $entity_form = &$element['inline_entity_form'];
    }
    elseif (isset($element['entities'][$context['delta']]['form']['inline_entity_form'])) {
      $entity_form = &$element['entities'][$context['delta']]['form']['inline_entity_form'];
    }
    elseif (isset($element['form']['inline_entity_form'])) {
      $entity_form = &$element['form']['inline_entity_form'];
    }
    else {
      return;
    }
    $info = [
      'parent' => $context['items']->getEntity(),
      'field_name' => $context['items']->getFieldDefinition()->getName(),
      'delta' => $context['delta'],
      'widget_plugin_id' => $context['widget']->getPluginId(),
    ];
    $entity_form['#eca_ief_info'] = &$info;

    // On Ajax form rebuilds, set the manually built entity to be used.
    if ($form_state->isRebuilding() && ($entity = $form_state->get([
      'eca_ief',
      $info['parent']->uuid(),
      $info['field_name'],
      $info['delta'],
    ]))) {
      $entity_form['#entity'] = $entity;
      $entity_form['#default_value'] = $entity;
      // Make sure to use the manually built entity only once.
      $form_state->set([
        'eca_ief',
        $info['parent']->uuid(),
        $info['field_name'],
        $info['delta'],
      ], NULL);
    }
  }

  /**
   * Alters the "inline_entity_form_simple" widget.
   *
   * @param mixed &$element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed &$context
   *   The widget context.
   */
  public function fieldWidgetSingleElementInlineEntityFormSimpleFormAlter(&$element, FormStateInterface $form_state, &$context): void {
    $this->fieldWidgetSingleElementInlineEntityFormComplexFormAlter($element, $form_state, $context);
  }

  /**
   * Alters the "paragraphs" widget.
   *
   * @param mixed &$element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed &$context
   *   The widget context.
   */
  public function fieldWidgetSingleElementParagraphsFormAlter(&$element, FormStateInterface $form_state, &$context): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }

    $field_name = $context['items']->getFieldDefinition()->getName();
    $delta = $context['delta'];
    $widget_state = WidgetBase::getWidgetState($element['#field_parents'], $field_name, $form_state);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $widget_state['paragraphs'][$delta]['entity'] ?? $context['items']->get($delta)->entity;
    $parent = $context['items']->getEntity();
    $widget_plugin_id = $context['widget']->getPluginId();

    $this->triggerEvent->dispatchFromPlugin('form:ief_build', $element['subform'], $form_state, $paragraph, $parent, $field_name, $delta, $widget_plugin_id);
  }

  /**
   * Alters the "entity_reference_paragraphs" widget.
   *
   * @param mixed &$element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed &$context
   *   The widget context.
   */
  public function fieldWidgetSingleElementEntityReferenceParagraphsFormAlter(&$element, FormStateInterface $form_state, &$context): void {
    $this->fieldWidgetSingleElementParagraphsFormAlter($element, $form_state, $context);
  }

}
