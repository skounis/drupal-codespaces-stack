<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Plugin implementation of the ECA condition for an entity field value.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_value",
 *   label = @Translation("Entity: compare field value"),
 *   description = @Translation("Compares a field value with an expected custom value."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldValue extends StringComparisonBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   *
   * This class takes care of Token replacements on its own.
   */
  protected static bool $replaceTokens = FALSE;

  /**
   * The configured and tokenized field name.
   *
   * @var string|null
   */
  protected ?string $fieldName;

  /**
   * The configured value as expected field value.
   *
   * @var string|null
   */
  protected ?string $expectedValue;

  /**
   * The target field value to evaluate the expected value against.
   *
   * @var string|null
   */
  protected ?string $targetValue;

  /**
   * {@inheritdoc}
   */
  public function reset(): ConditionInterface {
    $this->fieldName = NULL;
    $this->expectedValue = NULL;
    $this->targetValue = NULL;
    return parent::reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    $target_value = $this->targetValue ?? $this->getTargetValue();
    if (NULL === $target_value && $this->getRightValue() === '') {
      // Since the StringComparisonBase always compares string values, we want
      // to make sure, that the evaluation will return FALSE for the very rare
      // situation, that an empty string is expected to be contained.
      return '_ENTITY_FIELD_VALUE_IS_NULL_';
    }
    return $target_value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->getExpectedValue();
  }

  /**
   * Get the configured and tokenized field name.
   *
   * @return string
   *   The tokenized field name.
   */
  protected function getFieldName(): string {
    if (!isset($this->fieldName)) {
      $this->fieldName = trim((string) $this->tokenService->replaceClear($this->configuration['field_name'] ?? ''));
    }
    return $this->fieldName;
  }

  /**
   * Get the configured and tokenized value as expected field value.
   *
   * @return string
   *   The expected value.
   */
  protected function getExpectedValue(): string {
    if (!isset($this->expectedValue)) {
      $this->expectedValue = trim((string) $this->tokenService->replaceClear($this->configuration['expected_value'] ?? ''));
    }
    return $this->expectedValue;
  }

  /**
   * Get the target field value to evaluate against.
   *
   * @return string|null
   *   The target field value as string or NULL if no value is present.
   */
  protected function getTargetValue(): ?string {
    $field_values = $this->getFieldValues();
    $num_items = count($field_values);
    switch ($num_items) {
      case 0:
        $target_value = NULL;
        break;

      case 1:
        $target_value = reset($field_values);
        break;

      default:
        // When the field contains multiple values, we evaluate against
        // every single item and stick with the first match found.
        $target_value = NULL;
        $negated = $this->isNegated();
        $this->configuration['negate'] = FALSE;
        foreach ($field_values as $field_value) {
          $this->targetValue = $field_value;
          if ($this->evaluate()) {
            $target_value = $field_value;
            break;
          }
        }
        $this->targetValue = NULL;
        $this->configuration['negate'] = $negated;
    }
    return $target_value;
  }

  /**
   * Get a list of field values to evaluate against.
   *
   * @return string[]
   *   The list of field values.
   */
  protected function getFieldValues(): array {
    $field_name = $this->getFieldName();
    $entity = $this->getEntity();
    $values = [];
    $options = [
      'auto_append' => FALSE,
      'auto_item' => FALSE,
      'access' => FALSE,
    ];
    if ($entity && ($list = $this->getTypedProperty($entity->getTypedData(), $field_name, $options))) {
      if (!($list instanceof TraversableTypedDataInterface)) {
        $list = [$list];
      }
      /**
       * @var \Drupal\Core\TypedData\TypedDataInterface $property
       */
      foreach ($list as $property) {
        if ($property instanceof ComplexDataInterface) {
          $main_property = $property->getDataDefinition()->getMainPropertyName();
          if ($main_property !== NULL) {
            $property = $property->get($main_property);
          }
        }
        if ($property instanceof BooleanData) {
          $values[] = (string) (int) $property->getValue();
        }
        elseif ($property instanceof PrimitiveInterface) {
          $values[] = (string) $property->getValue();
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'expected_value' => '',
      'field_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#default_value' => $this->configuration['field_name'] ?? '',
      '#description' => $this->t('The name of the field whose value to compare.'),
      '#weight' => -90,
    ];
    $form['expected_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expected field value'),
      '#description' => $this->t('The expected value.'),
      '#default_value' => $this->configuration['expected_value'] ?? '',
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['expected_value'] = $form_state->getValue('expected_value');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Get the entity to act upon.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL if not found.
   */
  public function getEntity(): ?EntityInterface {
    return $this->getValueFromContext('entity');
  }

}
