<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\EntityOriginalTrait;

/**
 * Class for the original field value.
 *
 * <p>Plugin implementation of the ECA condition for an entity original
 * field value.</p>
 *
 * @EcaCondition(
 *   id = "eca_entity_original_field_value",
 *   label = @Translation("Entity: original has field value"),
 *   description = @Translation("Compares a field value of an entities <em>original</em>  by specific properties."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityOriginalFieldValue extends EntityFieldValue {

  use EntityOriginalTrait;

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?EntityInterface {
    if (!($entity = parent::getEntity())) {
      return NULL;
    }
    return $this->getOriginal($entity);
  }

}
