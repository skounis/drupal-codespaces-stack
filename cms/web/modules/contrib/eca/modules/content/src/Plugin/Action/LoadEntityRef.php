<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Load referenced entity into token environment.
 *
 * @Action(
 *   id = "eca_token_load_entity_ref",
 *   label = @Translation("Entity: load via reference"),
 *   description = @Translation("Load a single entity that is referenced by an entity from the current scope or by certain properties, and store it as a token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class LoadEntityRef extends LoadEntity {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  protected function doLoadEntity(?EntityInterface $entity = NULL): ?EntityInterface {
    $entity = parent::doLoadEntity($entity);
    $this->entity = NULL;
    if (is_null($entity)) {
      return NULL;
    }
    if (!($entity instanceof EntityInterface)) {
      throw new \InvalidArgumentException('No entity provided.');
    }
    $reference_field_name = trim((string) $this->tokenService->replace($this->configuration['field_name_entity_ref']));
    if (($entity instanceof FieldableEntityInterface) && $entity->hasField($reference_field_name)) {
      $item_list = $entity->get($reference_field_name);
      if (!($item_list instanceof EntityReferenceFieldItemListInterface)) {
        throw new \InvalidArgumentException(sprintf('Field %s is not an entity reference field for entity type %s/%s.', $reference_field_name, $entity->getEntityTypeId(), $entity->bundle()));
      }
      $referenced = $item_list->referencedEntities();
      $this->entity = $referenced ? reset($referenced) : NULL;
      return $this->entity;
    }
    elseif ($property = $this->getTypedProperty($entity->getTypedData(), $reference_field_name, [
      'access' => FALSE,
      'auto_item' => FALSE,
    ])) {
      if (is_scalar($property->getValue())) {
        $property = $property->getParent();
      }
      if (isset($property->entity) && ($property->entity instanceof EntityInterface)) {
        $this->entity = $property->entity;
      }
      elseif ($property->getValue() instanceof EntityInterface) {
        $this->entity = $property->getValue();
      }
      return $this->entity;
    }

    throw new \InvalidArgumentException(sprintf('Field %s does not exist for entity type %s/%s.', $reference_field_name, $entity->getEntityTypeId(), $entity->bundle()));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name_entity_ref' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name_entity_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name entity reference'),
      '#default_value' => $this->configuration['field_name_entity_ref'],
      '#description' => $this->t('The field name of the entity reference.'),
      '#weight' => -80,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name_entity_ref'] = $form_state->getValue('field_name_entity_ref');
    parent::submitConfigurationForm($form, $form_state);
  }

}
