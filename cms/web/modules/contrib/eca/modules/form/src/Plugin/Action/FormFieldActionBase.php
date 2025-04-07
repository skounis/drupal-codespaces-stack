<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\FormFieldPluginTrait;

/**
 * Base class for form field actions.
 */
abstract class FormFieldActionBase extends FormActionBase {

  use FormFieldPluginTrait;

  /**
   * Whether this action supports multiple form fields to operate with.
   *
   * @var bool
   */
  protected bool $supportsMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    $original_field_name = $this->configuration['field_name'];

    $missing_form_fields = [];
    foreach ($this->extractFormFieldNames($original_field_name) as $field_name) {
      $this->configuration['field_name'] = $field_name;
      if (is_null($this->getTargetElement())) {
        $missing_form_fields[] = $field_name;
      }
    }

    if ($missing_form_fields) {
      $form_field_result = AccessResult::forbidden(sprintf("The following form fields were not found: %s", implode(', ', $missing_form_fields)));
    }
    else {
      $form_field_result = AccessResult::allowed();
    }

    $result = $result->andIf($form_field_result);

    // Restoring the original config entry.
    $this->configuration['field_name'] = $original_field_name;
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   *
   * Optionally allows execution on multiple field names, calling ::doExecute()
   * for each single field name.
   */
  public function execute(): void {
    $original_field_name = $this->configuration['field_name'];

    $field_names = $this->extractFormFieldNames($original_field_name);
    if (!$this->supportsMultiple && count($field_names) > 1) {
      throw new \InvalidArgumentException("This action does not support multiple fields.");
    }

    foreach ($field_names as $field_name) {
      $this->configuration['field_name'] = $field_name;
      $this->doExecute();
    }

    // Restoring the original config entry.
    $this->configuration['field_name'] = $original_field_name;
  }

  /**
   * Actually performs action execution.
   *
   * This is only relevant when not overriding ::execute() and instead making
   * use of the implementation resided in
   * \Drupal\eca_form\Plugin\Action\FormFieldActionBase::execute().
   */
  protected function doExecute(): void {}

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return $this->defaultFormFieldConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = $this->buildFormFieldConfigurationForm($form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateFormFieldConfigurationForm($form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
    if (mb_strpos($form_state->getValue('field_name', ''), ',') !== FALSE) {
      $form_state->setError($form['field_name'], $this->t('This action does not support multiple fields.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->submitFormFieldConfigurationForm($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Extracts form field names from the given user input.
   *
   * Runs through token replacement, and the input will be transformed into
   * an array of field names, ready for being evaluated.
   *
   * @param string $user_input
   *   The user input containing configured form field names.
   *
   * @return array
   *   The extracted form field names, ready for evaluation.
   */
  protected function extractFormFieldNames(string $user_input): array {
    return array_map('trim', explode(',', (string) $this->tokenService->replace($user_input)));
  }

}
