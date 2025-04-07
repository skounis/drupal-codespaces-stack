<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Provides an event when a content entity revision is being created.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_content\Event
 */
class ContentEntityRevisionCreate extends ContentEntityBaseContentEntity {

  /**
   * The original entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $original;

  /**
   * Flag to keep untranslatable fields.
   *
   * @var bool
   */
  protected bool $keepUntranslatableFields;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $new_revision
   *   The new revision.
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The entity types.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The original entity.
   * @param bool|null $keep_untranslatable_fields
   *   The flag to keep untranslatable fields.
   */
  public function __construct(ContentEntityInterface $new_revision, ContentEntityTypes $entity_types, ContentEntityInterface $entity, ?bool $keep_untranslatable_fields) {
    parent::__construct($entity, $entity_types);
    $this->entity = $new_revision;
    $this->original = $entity;
    $this->keepUntranslatableFields = (bool) $keep_untranslatable_fields;
  }

  /**
   * Gets the new revision.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The new revision.
   */
  public function getNewRevision(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Get the original entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The original entity.
   */
  public function getOriginalEntity(): ContentEntityInterface {
    return $this->original;
  }

  /**
   * Gets the flag to keep untranslatable fields.
   *
   * @return bool
   *   The flag to keep untranslatable fields.
   */
  public function isKeepUntranslatableFields(): bool {
    return $this->keepUntranslatableFields;
  }

}
