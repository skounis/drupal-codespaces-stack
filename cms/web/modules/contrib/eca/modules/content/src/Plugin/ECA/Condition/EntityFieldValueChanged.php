<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\EntityOriginalTrait;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Plugin implementation of the ECA condition for changed entity field value.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_value_changed",
 *   label = @Translation("Entity: field value changed"),
 *   description = @Translation("Evaluates against the change of a value field of an entity."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldValueChanged extends ConditionBase {

  use EntityOriginalTrait;
  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    $field_name = $this->tokenService->replaceClear($this->configuration['field_name']);
    $options = ['access' => FALSE, 'auto_item' => FALSE];
    $original = $this->getOriginal($entity);
    if (($entity instanceof EntityInterface) && isset($original) && ($original instanceof EntityInterface) && ($property = $this->getTypedProperty($entity->getTypedData(), $field_name, $options)) && ($original_property = $this->getTypedProperty($original->getTypedData(), $field_name, $options))) {
      $value = $property->getValue();
      $original_value = $original_property->getValue();
      if (is_countable($value) && count($value) !== count($original_value)) {
        return $this->negationCheck(TRUE);
      }

      $dataDefinition = $property->getDataDefinition();
      if ($dataDefinition instanceof FieldConfigInterface || $dataDefinition instanceof BaseFieldDefinition) {
        $type = $dataDefinition->getFieldStorageDefinition()->getType();
        if (in_array($type, ['boolean', 'entity_reference'], TRUE)) {
          foreach ($value as $key => $item) {
            switch ($type) {
              case 'boolean':
                $value[$key]['value'] = (bool) $item['value'];
                $original_value[$key]['value'] = (bool) $original_value[$key]['value'];
                break;

              case 'entity_reference':
                $value[$key] = [
                  'target_id' => $item['target_id'],
                  'weight' => $item['weight'] ?? 0,
                ];
                $original_value[$key] = [
                  'target_id' => $original_value[$key]['target_id'],
                  'weight' => $original_value[$key]['weight'] ?? $value[$key]['weight'],
                ];
                break;

              default:
                ksort($value[$key]);

            }
          }
        }
      }
      return $this->negationCheck($value !== $original_value);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
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
      '#description' => $this->t('The field name of the entity to check, if its value has changed.'),
      '#default_value' => $this->configuration['field_name'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
