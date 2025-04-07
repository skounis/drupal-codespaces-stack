<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Service\ContentEntityTypes;
use Drupal\eca_content\Plugin\Validation\Constraint\EcaConstraintValidator;

/**
 * Provides an event when a content entity is undergoing validation.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_content\Event
 */
class ContentEntityValidate extends ContentEntityBaseContentEntity {

  /**
   * The validator.
   *
   * @var \Drupal\eca_content\Plugin\Validation\Constraint\EcaConstraintValidator
   */
  protected EcaConstraintValidator $validator;

  /**
   * ContentEntityValidate constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity type service.
   * @param \Drupal\eca_content\Plugin\Validation\Constraint\EcaConstraintValidator $validator
   *   The validator.
   */
  public function __construct(ContentEntityInterface $entity, ContentEntityTypes $entity_types, EcaConstraintValidator $validator) {
    parent::__construct($entity, $entity_types);
    $this->validator = $validator;
  }

  /**
   * Get the validator.
   *
   * @return \Drupal\eca_content\Plugin\Validation\Constraint\EcaConstraintValidator
   *   The validator.
   */
  public function getValidator(): EcaConstraintValidator {
    return $this->validator;
  }

}
