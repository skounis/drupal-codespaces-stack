<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Event\ContentEntityEventInterface;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Base class for content entity related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class ContentEntityBaseContentEntity extends ContentEntityBase implements ContentEntityEventInterface {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * ContentEntityBaseEntity constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity type service.
   */
  public function __construct(ContentEntityInterface $entity, ContentEntityTypes $entity_types) {
    $this->entity = $entity;
    $this->entityTypes = $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

}
