<?php

namespace Drupal\yoast_seo\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * A class to encapsulate entity analysis results.
 */
class EntityPagePreview implements EntityPreviewInterface {

  /**
   * The entity being previewed.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The language the preview is in.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
    $this->language = $entity->language();
  }

  /**
   * {@inheritdoc}
   */
  public function language() {
    return $this->language;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity;
  }

}
