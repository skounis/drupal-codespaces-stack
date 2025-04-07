<?php

namespace Drupal\eca_render\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca_render\Plugin\views\field\EcaRender;

/**
 * Dispatched when an ECA Views field is being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderViewsFieldEvent extends EcaRenderEventBase implements EntityEventInterface {

  /**
   * The render array build.
   *
   * @var array
   */
  protected array $build;

  /**
   * The field plugin.
   *
   * @var \Drupal\eca_render\Plugin\views\field\EcaRender
   */
  protected EcaRender $fieldPlugin;

  /**
   * The main entity of the Views row.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Relationship entities of the Views row.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $relationshipEntities;

  /**
   * Constructs a new EcaRenderViewsFieldEvent object.
   *
   * @param \Drupal\eca_render\Plugin\views\field\EcaRender $field_plugin
   *   The field plugin.
   * @param array &$build
   *   The render array build.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The main entity of the Views row.
   * @param \Drupal\Core\Entity\EntityInterface[] $relationship_entities
   *   Relationship entities of the Views row.
   */
  public function __construct(EcaRender $field_plugin, array &$build, EntityInterface $entity, array $relationship_entities) {
    $this->fieldPlugin = $field_plugin;
    $this->build = &$build;
    $this->entity = $entity;
    $this->relationshipEntities = $relationship_entities;
  }

  /**
   * Get the field plugin.
   *
   * @return \Drupal\eca_render\Plugin\views\field\EcaRender
   *   The field plugin.
   */
  public function getFieldPlugin(): EcaRender {
    return $this->fieldPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Get the relationship entities of the Views row.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Relationship entities.
   */
  public function getRelationshipEntities(): array {
    return $this->relationshipEntities;
  }

}
