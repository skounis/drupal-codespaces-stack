<?php

namespace Drupal\eca_content\Event;

use Drupal\eca\Service\ContentEntityTypes;

/**
 * Base class for entity bundle related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class ContentEntityBaseBundle extends ContentEntityBase {

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * The bundle.
   *
   * @var string
   */
  protected string $bundle;

  /**
   * ContentEntityBaseBundle constructor.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity type service.
   */
  public function __construct(string $entity_type_id, string $bundle, ContentEntityTypes $entity_types) {
    $this->entityTypeId = $entity_type_id;
    $this->bundle = $bundle;
    $this->entityTypes = $entity_types;
  }

  /**
   * Gets the entity type id.
   *
   * @return string
   *   The entity type id.
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Gets the bundle.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(): string {
    return $this->bundle;
  }

}
