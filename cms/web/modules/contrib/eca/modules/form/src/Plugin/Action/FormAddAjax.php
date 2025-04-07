<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_form\HookHandler;

/**
 * Adds an Ajax handler to a form.
 *
 * @Action(
 *   id = "eca_form_add_ajax",
 *   label = @Translation("Form: add Ajax handler"),
 *   description = @Translation("Enhances an existing form field element with an Ajax handler for refreshing parts of a form without refreshing the whole page."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddAjax extends FormFieldActionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * Temporarily holds the most recent form array build, if provided externally.
   *
   * @var array|null
   */
  protected ?array $currentForm = NULL;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'disable_validation_errors' => FALSE,
      'validate_fields' => '',
      'target' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['disable_validation_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable validation errors'),
      '#description' => $this->t('Enable this option to completely suppress validation errors.'),
      '#default_value' => $this->configuration['disable_validation_errors'],
      '#weight' => -10,
    ];
    $form['validate_fields'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Validate form fields'),
      '#description' => $this->t('Machine names of form fields that should be validated. Define multiple values separated with commas. Example: <em>first_name,last_name</em>. When no fields are defined at all and validation is not disabled above, then the whole form will be validated.'),
      '#weight' => -9,
      '#default_value' => $this->configuration['validate_fields'],
    ];
    $form['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target'),
      '#description' => $this->t('The machine name of the form element target to refresh via Ajax. When empty, then the whole form will be refreshed.'),
      '#weight' => -8,
      '#default_value' => $this->configuration['target'],
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['field_name']['#description'] .= ' ' . $this->t('When this form element got Ajax handling attached, using this element will automatically submit the form. Therefore, you can react upon that with regular form events like <em>Build form</em>, <em>Submit form</em> and when validation is enabled, also <em>Validate form</em>.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['disable_validation_errors'] = !empty($form_state->getValue('disable_validation_errors'));
    $this->configuration['validate_fields'] = $form_state->getValue('validate_fields');
    $this->configuration['target'] = $form_state->getValue('target');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if (trim($this->configuration['target']) !== '') {
      $original_field_name = $this->configuration['field_name'];
      $this->configuration['field_name'] = $this->tokenService->replace($this->configuration['target']);
      $result = $result->andIf(AccessResult::allowedIf(!is_null($this->getTargetElement())));
      $this->configuration['field_name'] = $original_field_name;
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $element = &$this->getTargetElement();
    $target_name = '';
    if (isset($element['widget'])) {
      // Automatically jump to the widget form element, as it's being build
      // by \Drupal\Core\Field\WidgetBase::form().
      $element = &$element['widget'];
    }
    if (trim($this->configuration['target']) !== '') {
      $original_field_name = $this->configuration['field_name'];
      $target_name = $this->tokenService->replace($this->configuration['target']);
      $this->configuration['field_name'] = $target_name;
      $target_element = &$this->getTargetElement();
      $this->configuration['field_name'] = $original_field_name;
    }
    else {
      $form_state = $this->getCurrentFormState();
      if ($form_state && ($target_name = $form_state->getFormObject()->getFormId())) {
        $target_element = &$this->getCurrentForm();
      }
      else {
        $target_element = NULL;
      }
    }

    if (!$element || !$target_element) {
      return;
    }

    $wrapper_id = Html::getUniqueId($target_name . '-ajax-wrapper');
    $target_element['#prefix'] = '<div id="' . $wrapper_id . '">' . ($target_element['#prefix'] ?? '');
    $target_element['#suffix'] = ($target_element['#suffix'] ?? '') . '</div>';

    $element['#ajax'] = [
      'callback' => [$this, 'ajax'],
      'wrapper' => $wrapper_id,
      'method' => 'html',
    ];
    $element['#executes_submit_callback'] = TRUE;

    if ($this->configuration['disable_validation_errors']) {
      $element['#limit_validation_errors'] = [];
    }

    if (in_array(
      $element['#type'] ?? NULL,
      ['textfield', 'textarea', 'text_format'],
      TRUE
    )) {
      // Re-focus on text-based fields could mean that you will never get away
      // from them. To avoid this, use the option to disable refocus.
      $element['#ajax']['disable-refocus'] = TRUE;
    }

    $validate_fields = trim($this->configuration['validate_fields']) !== '' ? DataTransferObject::buildArrayFromUserInput((string) $this->tokenService->replace($this->configuration['validate_fields'])) : [];
    if (!empty($validate_fields)) {
      // These are the supported separators. The first one is the official one,
      // the others are unofficially supported.
      // @see \Drupal\eca\Plugin\FormFieldPluginTrait::getTargetElement()
      $separators = ['][', ':', '.'];

      foreach ($validate_fields as $validate_field) {
        foreach ($separators as $separator) {
          if (mb_strpos($validate_field, $separator) !== FALSE) {
            $validate_field = explode($separator, $validate_field);
            break;
          }
        }
        if (!is_array($validate_field)) {
          $validate_field = [$validate_field];
        }
        $element['#limit_validation_errors'][] = $validate_field;
      }
    }

    $submit_handler = [HookHandler::class, 'submit'];
    if (empty($element['#submit']) || !in_array($submit_handler, $element['#submit'], TRUE)) {
      $element['#submit'][] = $submit_handler;
    }
    $element['#submit'][] = [static::class, 'ajaxSubmit'];
  }

  /**
   * Ajax callback coming from added handlers.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element to refresh via Ajax.
   */
  public function ajax(array $form, FormStateInterface $form_state): array {
    if ($triggering_element = &$form_state->getTriggeringElement()) {
      $array_parents = $triggering_element['#array_parents'] ?? [];
      while ($array_parents) {
        $parent_element = &NestedArray::getValue($form, $array_parents);
        if ($parent_element && isset($parent_element['#group']) && isset($form[$parent_element['#group']]['#open'])) {
          // When the triggering element is placed inside a grouping element,
          // the generally expected state of it is to be opened. Otherwise
          // it would not be visible to the user.
          // @todo Find a common solution to guarantee not losing any opened
          // and closed states of form elements when refreshed via Ajax.
          $form[$parent_element['#group']]['#open'] = TRUE;
        }
        array_pop($array_parents);
      }
    }

    if (trim($this->configuration['target']) !== '') {
      $original_field_name = $this->configuration['field_name'];
      $target_name = $this->tokenService->replace($this->configuration['target']);
      $this->configuration['field_name'] = $target_name;

      // Use the provided form array build as current form, because this holds
      // the most recent state. According events may hold an outdated state.
      // Not relevant for the else-block below, this is only relevant when
      // requesting for a specific target element.
      $this->currentForm = &$form;
      $target_element = &$this->getTargetElement();
      unset($this->currentForm);
      $this->currentForm = NULL;

      $this->configuration['field_name'] = $original_field_name;
      if ($target_element) {
        if (!empty($target_element['#array_parents'])) {
          // When provided, use the updated render array build.
          return NestedArray::getValue($form, $target_element['#array_parents']) ?? [];
        }
        return $target_element;
      }
    }
    else {
      $element = &$this->getCurrentForm();
      if ($element && !empty($element['#array_parents'])) {
        // Provided form of the event is a subform, so return only this part.
        return NestedArray::getValue($form, $element['#array_parents']) ?? [];
      }
      return $form;
    }
    return [];
  }

  /**
   * Ajax submit handler that sets the form to rebuild.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function ajaxSubmit(array $form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function &getCurrentForm(): ?array {
    if (isset($this->currentForm)) {
      return $this->currentForm;
    }
    $current_form = &parent::getCurrentForm();
    return $current_form;
  }

}
