<?php

namespace Drupal\eca_render\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eca\Event\EntityApplianceTrait;

/**
 * Dispatched when contextual links are being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderContextualLinksEvent extends EcaRenderEventBase {

  use EntityApplianceTrait;

  /**
   * The current links array.
   *
   * @var array
   */
  protected array $links;

  /**
   * The link group.
   *
   * @var string
   */
  protected string $group;

  /**
   * The route parameters.
   *
   * @var array
   */
  protected array $routeParameters;

  /**
   * The render array build.
   *
   * @var array
   */
  protected array $build;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new EcaRenderContextualLinksEvent object.
   *
   * @param array &$links
   *   The current links array.
   * @param string $group
   *   The link group.
   * @param array $route_parameters
   *   The route parameters.
   * @param array &$build
   *   The render array build.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array &$links, string $group, array $route_parameters, array &$build, EntityTypeManagerInterface $entity_type_manager) {
    $this->links = &$links;
    $this->group = $group;
    $this->routeParameters = $route_parameters;
    $this->build = &$build;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

  /**
   * Get the current links array.
   *
   * @return array
   *   The links array.
   */
  public function &getLinks(): array {
    return $this->links;
  }

  /**
   * Get the link group.
   *
   * @return string
   *   The link group.
   */
  public function getGroup(): string {
    return $this->group;
  }

  /**
   * Get the route parameters.
   *
   * @return array
   *   The route parameters.
   */
  public function getRouteParameters(): array {
    return $this->routeParameters;
  }

  /**
   * Get the entity, if available.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL if not available.
   */
  public function getEntity(): ?EntityInterface {
    foreach ($this->routeParameters as $k => $v) {
      if (is_string($k) && is_scalar($v) && $this->entityTypeManager->hasDefinition($k) && ($entity = $this->entityTypeManager->getStorage($k)->load($v))) {
        return $entity;
      }
    }
    return NULL;
  }

}
