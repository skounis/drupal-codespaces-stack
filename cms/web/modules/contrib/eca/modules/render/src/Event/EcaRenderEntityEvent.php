<?php

namespace Drupal\eca_render\Event;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityApplianceTrait;
use Drupal\eca\Event\EntityEventInterface;

/**
 * Dispatched when an entity is being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderEntityEvent extends EcaRenderEventBase implements EntityEventInterface {

  use EntityApplianceTrait;

  /**
   * The entity in scope.
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
   * The entity display.
   *
   * @var \Drupal\Core\Entity\Display\EntityDisplayInterface
   */
  protected EntityDisplayInterface $display;

  /**
   * The display mode.
   *
   * @var string
   */
  protected string $viewMode;

  /**
   * Constructs a new EcaRenderEntityEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity in scope.
   * @param array &$build
   *   The render array build.
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The entity display.
   * @param string $view_mode
   *   The display mode.
   */
  public function __construct(EntityInterface $entity, array &$build, EntityDisplayInterface $display, string $view_mode) {
    $this->entity = $entity;
    $this->build = &$build;
    $this->display = $display;
    $this->viewMode = $view_mode;
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
   * Get the entity display.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The entity display.
   */
  public function getDisplay(): EntityDisplayInterface {
    return $this->display;
  }

  /**
   * Get the view mode.
   *
   * @return string
   *   The view mode.
   */
  public function getViewMode(): string {
    return $this->viewMode;
  }

}
