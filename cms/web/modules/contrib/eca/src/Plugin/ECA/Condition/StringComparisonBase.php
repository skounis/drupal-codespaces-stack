<?php

namespace Drupal\eca\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Base class for ECA condition plugins to compare strings.
 */
abstract class StringComparisonBase extends ConditionBase {

  use PluginFormTrait;

  public const COMPARE_EQUALS = 'equal';
  public const COMPARE_BEGINS_WITH = 'beginswith';
  public const COMPARE_ENDS_WITH = 'endswith';
  public const COMPARE_CONTAINS = 'contains';
  public const COMPARE_GREATERTHAN = 'greaterthan';
  public const COMPARE_LESSTHAN = 'lessthan';
  public const COMPARE_ATMOST = 'atmost';
  public const COMPARE_ATLEAST = 'atleast';

  public const COMPARE_TYPE_VALUE = 'value';
  public const COMPARE_TYPE_COUNT = 'count';
  public const COMPARE_TYPE_LEXICAL = 'lexical';
  public const COMPARE_TYPE_NATURAL = 'natural';
  public const COMPARE_TYPE_NUMERIC = 'numeric';

  /**
   * This flag indicates whether Token replacement should be applied beforehand.
   *
   * @var bool
   */
  protected static bool $replaceTokens = TRUE;

  /**
   * Get the left/first string value for comparison.
   *
   * @return string
   *   The left value for comparison.
   */
  abstract protected function getLeftValue(): string;

  /**
   * Get the right/second string value for comparison.
   *
   * @return string
   *   The right value for comparison.
   */
  abstract protected function getRightValue(): string;

  /**
   * Get the selected comparison operator.
   *
   * @return string
   *   The comparison operator.
   */
  protected function getOperator(): string {
    $operator = $this->configuration['operator'] ?? static::COMPARE_EQUALS;
    if ($operator === '_eca_token') {
      $operator = $this->getTokenValue('operator', static::COMPARE_EQUALS);
    }
    return $operator;
  }

  /**
   * Get the comparison type.
   *
   * @return string
   *   The comparison type.
   */
  protected function getType(): string {
    $type = $this->configuration['type'] ?? static::COMPARE_TYPE_VALUE;
    if ($type === '_eca_token') {
      $type = $this->getTokenValue('type', static::COMPARE_TYPE_VALUE);
    }
    return $type;
  }

  /**
   * Whether the comparison is case sensitive or not.
   *
   * @return bool
   *   Returns TRUE if case sensitive, FALSE otherwise.
   */
  protected function caseSensitive(): bool {
    return $this->configuration['case'];
  }

  /**
   * {@inheritdoc}
   */
  final public function evaluate(): bool {
    if (static::$replaceTokens) {
      $leftValue = $this->tokenService->replace($this->getLeftValue());
      $rightValue = $this->tokenService->replace($this->getRightValue());
    }
    else {
      $leftValue = $this->getLeftValue();
      $rightValue = $this->getRightValue();
    }

    if (!$this->caseSensitive()) {
      $leftValue = mb_strtolower($leftValue);
      $rightValue = mb_strtolower($rightValue);
    }

    switch ($this->getType()) {
      case static::COMPARE_TYPE_NUMERIC:
        if (!is_numeric($leftValue) || !is_numeric($rightValue)) {
          return FALSE;
        }
        break;

      case static::COMPARE_TYPE_LEXICAL:
        // Prepend the value with a constant character to force string
        // comparison, even if values were numeric.
        $leftValue = 'a' . $leftValue;
        $rightValue = 'a' . $rightValue;
        break;

      case static::COMPARE_TYPE_NATURAL:
        $leftValue = 0;
        $rightValue = strnatcmp((string) $leftValue, $rightValue);
        break;

      case static::COMPARE_TYPE_COUNT:
        $leftValue = mb_strlen($leftValue);
        $rightValue = mb_strlen($rightValue);
        break;
    }

    $result = FALSE;

    switch ($this->getOperator()) {
      case static::COMPARE_EQUALS:
        $result = $leftValue === $rightValue;
        break;

      case static::COMPARE_BEGINS_WITH:
        $result = mb_strpos($leftValue, $rightValue) === 0;
        break;

      case static::COMPARE_ENDS_WITH:
        $result = mb_strpos($leftValue, $rightValue) === (mb_strlen($leftValue) - mb_strlen($rightValue));
        break;

      case static::COMPARE_CONTAINS:
        $result = mb_strpos($leftValue, $rightValue) !== FALSE;
        break;

      case static::COMPARE_GREATERTHAN:
        $result = $leftValue > $rightValue;
        break;

      case static::COMPARE_LESSTHAN:
        $result = $leftValue < $rightValue;
        break;

      case static::COMPARE_ATMOST:
        $result = $leftValue <= $rightValue;
        break;

      case static::COMPARE_ATLEAST:
        $result = $leftValue >= $rightValue;
        break;
    }

    return $this->negationCheck($result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'operator' => static::COMPARE_EQUALS,
      'type' => static::COMPARE_TYPE_VALUE,
      'case' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Comparison operator'),
      '#description' => $this->t('The available comparison operators like <em>equals</em> or <em>less than</em>.'),
      '#default_value' => $this->getOperator(),
      '#options' => $this->getOptions('operator'),
      '#weight' => -80,
      '#eca_token_select_option' => TRUE,
    ];
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Comparison type'),
      '#description' => $this->t('The type of the comparison.'),
      '#default_value' => $this->getType(),
      '#options' => $this->getOptions('type'),
      '#weight' => -60,
      '#eca_token_select_option' => TRUE,
    ];
    $form['case'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case sensitive comparison'),
      '#description' => $this->t('Compare the values based on case sensitivity.'),
      '#default_value' => $this->caseSensitive(),
      '#weight' => -50,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operator'] = $form_state->getValue('operator');
    $this->configuration['type'] = $form_state->getValue('type');
    $this->configuration['case'] = !empty($form_state->getValue('case'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Returns a keyed array of values with all available options.
   *
   * This can be overwritten by sub-classes if their implementation requires
   * a different set of options.
   *
   * @param string $id
   *   The id of the configuration value for which to receive the options.
   *
   * @return array|null
   *   The keyed array with option values. NULL if the field $id has no options.
   */
  protected function getOptions(string $id): ?array {
    if ($id === 'operator') {
      return [
        static::COMPARE_EQUALS => $this->t('equals'),
        static::COMPARE_BEGINS_WITH => $this->t('begins with'),
        static::COMPARE_ENDS_WITH => $this->t('ends with'),
        static::COMPARE_CONTAINS => $this->t('contains'),
        static::COMPARE_GREATERTHAN => $this->t('greater than'),
        static::COMPARE_LESSTHAN => $this->t('less than'),
        static::COMPARE_ATMOST => $this->t('at most'),
        static::COMPARE_ATLEAST => $this->t('at least'),
      ];
    }
    if ($id === 'type') {
      return [
        static::COMPARE_TYPE_VALUE => $this->t('Value'),
        static::COMPARE_TYPE_NATURAL => $this->t('Natural order'),
        static::COMPARE_TYPE_NUMERIC => $this->t('Numeric order'),
        static::COMPARE_TYPE_LEXICAL => $this->t('Lexical order'),
        static::COMPARE_TYPE_COUNT => $this->t('Character count'),
      ];
    }
    return NULL;
  }

}
