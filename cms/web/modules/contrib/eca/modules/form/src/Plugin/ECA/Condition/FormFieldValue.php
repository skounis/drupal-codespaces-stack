<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\Plugin\FormFieldPluginTrait;

/**
 * Compares a submitted form field value.
 *
 * @EcaCondition(
 *   id = "eca_form_field_value",
 *   label = @Translation("Form field: compare submitted value"),
 *   description = @Translation("Compares a submitted form field value."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class FormFieldValue extends StringComparisonBase {

  use FormFieldPluginTrait;

  /**
   * The configured and expected field value.
   *
   * @var string|null
   */
  protected ?string $expectedValue = NULL;

  /**
   * The current value in the form.
   *
   * @var string|null
   */
  protected ?string $currentValue = NULL;

  /**
   * {@inheritdoc}
   *
   * The left value must not be replaced with Tokens, as this may be arbitrary
   * user input, including from untrusted users.
   */
  protected static bool $replaceTokens = FALSE;

  /**
   * {@inheritdoc}
   */
  public function reset(): ConditionInterface {
    $this->expectedValue = NULL;
    $this->currentValue = NULL;
    return parent::reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    return $this->currentValue ?? $this->getCurrentValue();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->getExpectedValue();
  }

  /**
   * Get the configured and tokenized value as expected field value.
   *
   * @return string
   *   The expected field value.
   */
  protected function getExpectedValue(): string {
    if (!isset($this->expectedValue)) {
      $this->expectedValue = (string) $this->tokenService->replaceClear($this->configuration['field_value']);
    }
    return $this->expectedValue;
  }

  /**
   * Get the current form value.
   *
   * @return string|null
   *   The current value. May be NULL if no value exists.
   */
  protected function getCurrentValue(): ?string {
    if (!$this->getCurrentFormState()) {
      // Since the StringComparisonBase always compares string values, we want
      // to make sure, that the evaluation will return FALSE when there is no
      // current form state available.
      return '_FORM_STATE_IS_MISSING_';
    }

    $original_field_name = $this->configuration['field_name'];
    $this->configuration['field_name'] = (string) $this->tokenService->replace($original_field_name);

    $value = $this->getSubmittedValue();
    if (is_array($value)) {
      // When the field contains multiple values, we evaluate against
      // every single item and stick with the first match found. If no match
      // was found, we stick with the first found value, that mostly would then
      // finally evaluate to be false.
      $first_val = NULL;
      $matched_val = NULL;
      $negated = $this->isNegated();
      $this->configuration['negate'] = FALSE;
      array_walk_recursive($value, function ($v, $k) use (&$first_val, &$matched_val) {
        // This check includes a considering of integer 0 as unchecked checkbox.
        // @see \Drupal\Core\Render\Element\Checkboxes::getCheckedCheckboxes()
        if (is_scalar($v) && ($v !== 0) && trim((string) $v) !== '') {
          if (!isset($first_val)) {
            $first_val = $v;
          }
          if (!isset($matched_val)) {
            $this->currentValue = $v;
            if ($this->evaluate()) {
              $matched_val = $v;
            }
          }
        }
      });
      $this->expectedValue = NULL;
      $this->configuration['negate'] = $negated;
      $value = $matched_val ?? $first_val;
    }

    // Restoring the original config entry.
    $this->configuration['field_name'] = $original_field_name;

    if (is_scalar($value) || is_null($value)) {
      $value = trim((string) $value);
    }
    else {
      return '_VALUE_NOT_RESOLVABLE_TO_STRING_';
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_value' => '',
    ] + $this->defaultFormFieldConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field value'),
      '#description' => $this->t('The field value to compare.'),
      '#default_value' => $this->configuration['field_value'],
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    $form = $this->buildFormFieldConfigurationForm($form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateFormFieldConfigurationForm($form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_value'] = $form_state->getValue('field_value');
    $this->submitFormFieldConfigurationForm($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

}
