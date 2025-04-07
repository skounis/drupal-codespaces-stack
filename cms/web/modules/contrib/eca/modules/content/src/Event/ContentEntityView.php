<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Provides an event when a content entity is being viewed.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_content\Event
 */
class ContentEntityView extends ContentEntityBaseContentEntity {

  /**
   * The build.
   *
   * @var array
   */
  protected array $build;

  /**
   * The display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected EntityViewDisplayInterface $display;

  /**
   * The view mode.
   *
   * @var string
   */
  protected string $viewMode;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity types.
   * @param array $build
   *   The build.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display.
   * @param string $view_mode
   *   The view mode.
   */
  public function __construct(ContentEntityInterface $entity, ContentEntityTypes $entity_types, array &$build, EntityViewDisplayInterface $display, string $view_mode) {
    parent::__construct($entity, $entity_types);
    $this->build = $build;
    $this->display = $display;
    $this->viewMode = $view_mode;
  }

  /**
   * Gets the view mode.
   *
   * @return string
   *   The view mode.
   */
  public function getViewMode(): string {
    return $this->viewMode;
  }

}
