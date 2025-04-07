<?php

namespace Drupal\yoast_seo\Entity;

/**
 * Defines a common interface for entity preview objects.
 *
 * Entity preview objects provide info about the rendered state of an entity.
 *
 * @package Drupal\yoast_seo\Entity
 */
interface EntityPreviewInterface {

  /**
   * Retrieves the language of the preview.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The language object.
   */
  public function language();

  /**
   * Gets the entity that this is a preview for.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity object.
   */
  public function getEntity();

}
