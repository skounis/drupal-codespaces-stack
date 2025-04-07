<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Get the value of an entity field.
 *
 * @Action(
 *   id = "eca_get_field_value",
 *   label = @Translation("Entity: get field value"),
 *   description = @Translation("Get the value of any field in an entity and store it as a token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class GetFieldValue extends ConfigurableActionBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  protected function getFieldName(): string {
    return (string) $this->tokenService->replace($this->configuration['field_name']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The machine name of the field, that holds the value.'),
      '#default_value' => $this->configuration['field_name'],
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The field value will be loaded into this specified token.'),
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    if (!($object instanceof AccessibleInterface) || !($object instanceof EntityInterface)) {
      return $return_as_object ? $result : $result->isAllowed();
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $object;

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $entity->access('view', $account, TRUE);

    $field_name = $this->getFieldName();
    $options = [
      'access' => 'view',
      'auto_item' => !($entity instanceof FieldableEntityInterface && $entity->hasField($field_name)),
    ];
    $metadata = [];
    $read_target = $this->getTypedProperty($entity->getTypedData(), $field_name, $options, $metadata);
    if (!isset($metadata['access']) || (!$read_target && $metadata['access']->isAllowed())) {
      throw new \InvalidArgumentException(sprintf("The provided field %s does not exist as a property path on the %s entity having ID %s.", $field_name, $entity->getEntityTypeId(), $entity->id()));
    }
    $result = $result->andIf($metadata['access']);

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof EntityInterface)) {
      return;
    }
    $field_name = $this->getFieldName();
    $options = [
      'access' => 'view',
      'auto_item' => !($entity instanceof FieldableEntityInterface && $entity->hasField($field_name)),
    ];
    $metadata = [];
    $token_name = $this->configuration['token_name'];
    $property_path = $this->normalizePropertyPath($field_name);
    $read_target = $this->getTypedProperty($entity->getTypedData(), $property_path, $options, $metadata);
    if (!isset($metadata['access']) || (!$read_target && $metadata['access']->isAllowed())) {
      throw new \InvalidArgumentException(sprintf("The provided field %s does not exist as a property path on the %s entity having ID %s.", $property_path, $entity->getEntityTypeId(), $entity->id()));
    }

    $token_data = $read_target;

    // Traverse from bottom-up to see whether the configured field name
    // targets an entity reference list (or a single reference item).
    $path_items = explode('.', $property_path);
    $last_item = end($path_items);
    $delta_defined = FALSE;
    while (!$delta_defined && ($path_item = array_pop($path_items)) !== NULL) {
      if (ctype_digit($path_item)) {
        $delta_defined = TRUE;
        $path_item = (int) $path_item;
      }
      while ($read_target && $read_target->getName() !== $path_item) {
        $read_target = $read_target->getParent();
      }
    }
    if (!$delta_defined && ($read_target instanceof EntityReferenceFieldItemListInterface)) {
      // User input targets the whole reference list, use the entities from it.
      $token_data = $read_target->referencedEntities();
      if ((count($token_data) === 1) && ($read_target->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() === 1)) {
        $token_data = reset($token_data);
      }
    }
    elseif ($read_target instanceof EntityReferenceItem) {
      // User input targets a reference item, use the contained entity.
      // @todo The property is protected and should be accessed differently.
      if (isset($read_target->entity)) {
        $token_data = $read_target->entity;
      }
      else {
        $items = $read_target->getParent();
        if (($items instanceof EntityReferenceFieldItemListInterface) && ($entities = $items->referencedEntities())) {
          foreach ($items as $delta => $item) {
            if (($item === $read_target) || ($item->getValue() === $read_target->getValue())) {
              $token_data = $entities[$delta] ?? NULL;
              break;
            }
          }
        }
      }
    }
    elseif ($read_target && $read_target->getValue() instanceof EntityInterface) {
      // User input targets an entity, use it.
      $token_data = $read_target->getValue();
    }
    elseif (!$delta_defined && ($read_target instanceof FieldItemListInterface)) {
      // User input targets a list, use every value from it.
      $item_definition = $read_target->getItemDefinition();
      if ($item_definition instanceof ComplexDataDefinitionInterface) {
        $main_property = $item_definition->getMainPropertyName();
        $item_property = $item_definition->getPropertyDefinition($last_item) ? $last_item : $main_property;
      }
      else {
        $item_property = NULL;
      }
      $token_data = [];
      /**
       * @var \Drupal\Core\Field\FieldItemInterface $field_item
       */
      foreach ($read_target as $i => $field_item) {
        if (!isset($item_property)) {
          $token_data[$i] = $field_item->$last_item ?? $field_item->getValue();
        }
        elseif (isset($field_item->$item_property)) {
          $token_data[$i] = $field_item->$item_property;
        }
      }
      if ($read_target->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() === 1) {
        // Directly use the first entry when this is not a multi-value field.
        $token_data = $token_data ? reset($token_data) : NULL;
      }
    }

    $this->tokenService->addTokenData($token_name, $token_data);
  }

}
