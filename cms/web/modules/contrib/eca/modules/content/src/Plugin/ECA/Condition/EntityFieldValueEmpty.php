<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Plugin implementation of the ECA condition for empty entity field value.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_value_empty",
 *   label = @Translation("Entity: field value is empty"),
 *   description = @Translation("Evaluates if a value field of an entity is empty."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldValueEmpty extends ConditionBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    $field_name = $this->tokenService->replaceClear($this->configuration['field_name']);
    $property_path = $this->normalizePropertyPath($field_name);
    $options = ['access' => FALSE, 'auto_item' => FALSE];

    // Setting the default return result to be false, stops execution chain
    // when either the entity, field or property does not exist.
    $result = FALSE;

    if ($entity instanceof EntityInterface) {
      $is_empty = NULL;
      $property = NULL;
      while ($property_path && !($property = $this->getTypedProperty($entity->getTypedData(), $property_path, $options))) {
        // Property does not exist, which means it's empty.
        $is_empty = TRUE;
        $property_path = implode('.', array_slice(explode('.', $property_path), 0, -1));
      }
      if (($is_empty === NULL) && $property) {
        $is_empty = method_exists($property, 'isEmpty') ? $property->isEmpty() : empty($property->getValue());
      }
      if (!$is_empty) {
        // For stale entity references, the empty check may return a false
        // negative. Load the referenced entities to make sure, that they
        // really exist.
        if ($property instanceof EntityReferenceFieldItemListInterface) {
          $is_empty = empty($property->referencedEntities());
        }
        elseif ($property instanceof EntityReferenceItem) {
          $items = $property->getParent();
          if ($items instanceof EntityReferenceFieldItemListInterface) {
            $entities = $items->referencedEntities();
            if ($entities) {
              foreach ($items as $delta => $item) {
                if (($item === $property) || ($item->getValue() === $property->getValue())) {
                  $is_empty = !isset($entities[$delta]);
                  break;
                }
              }
            }
            else {
              $is_empty = TRUE;
            }
          }
        }
      }
      if ($is_empty !== NULL) {
        $result = $this->negationCheck($is_empty);
      }
    }

    return $result;
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
      '#description' => $this->t('The field name of the entity to check, if its value is empty.'),
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
