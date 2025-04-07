<?php

namespace Drupal\eca;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides helper functions to get and set entity originals.
 */
trait EntityOriginalTrait {

  /**
   * Get the original unchanged entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original unchanged entity or NULL, if that doesn't exist.
   */
  protected function getOriginal(EntityInterface $entity): ?EntityInterface {
    if (method_exists($entity, 'getOriginal')) {
      return $entity->getOriginal();
    }
    // @phpstan-ignore-next-line
    return $entity->original;
  }

  /**
   * Set the original unchanged entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\EntityInterface $original
   *   The original.
   */
  protected function setOriginal(EntityInterface $entity, EntityInterface $original): void {
    if (method_exists($entity, 'setOriginal')) {
      $entity->setOriginal($original);
    }
    else {
      // @phpstan-ignore-next-line
      $entity->original = $original;
    }
  }

}
