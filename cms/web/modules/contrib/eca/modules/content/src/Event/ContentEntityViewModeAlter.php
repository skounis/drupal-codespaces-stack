<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Provides an event when the view mode of a content entity can be altered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_content\Event
 */
class ContentEntityViewModeAlter extends ContentEntityBaseContentEntity {

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
   * @param string $view_mode
   *   The view mode.
   */
  public function __construct(ContentEntityInterface $entity, ContentEntityTypes $entity_types, string $view_mode) {
    parent::__construct($entity, $entity_types);
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

  /**
   * Sets the view mode.
   *
   * @param string $viewMode
   *   The view mode.
   */
  public function setViewMode(string $viewMode): void {
    $this->viewMode = $viewMode;
  }

}
