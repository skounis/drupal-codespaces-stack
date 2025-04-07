<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * Plugin implementation of the ECA condition for new entity.
 *
 * @EcaCondition(
 *   id = "eca_entity_is_new",
 *   label = @Translation("Entity: is new"),
 *   description = @Translation("Evaluates if an entity is new."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityIsNew extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if ($entity instanceof EntityInterface) {
      $result = $entity->isNew();
      return $this->negationCheck($result);
    }
    return FALSE;
  }

}
