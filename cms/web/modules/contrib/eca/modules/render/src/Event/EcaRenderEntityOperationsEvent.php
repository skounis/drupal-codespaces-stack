<?php

namespace Drupal\eca_render\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityApplianceTrait;
use Drupal\eca\Event\EntityEventInterface;

/**
 * Dispatched when operation links of an entity are being declared.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderEntityOperationsEvent extends EcaRenderEventBase implements EntityEventInterface {

  use EntityApplianceTrait;

  /**
   * The entity of the operation.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The render array build.
   *
   * @var array
   */
  protected array $build;

  /**
   * Constructs a new EcaRenderEntityOperationsEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array &$build
   *   The render array.
   */
  public function __construct(EntityInterface $entity, array &$build) {
    $this->entity = $entity;
    $this->build = &$build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

}
