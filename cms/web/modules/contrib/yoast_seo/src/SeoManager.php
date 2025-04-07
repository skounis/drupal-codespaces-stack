<?php

namespace Drupal\yoast_seo;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Utility service for the Real-Time SEO module.
 *
 * @package Drupal\yoast_seo
 */
class SeoManager {

  use StringTranslationTrait;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Type Bundle Info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Entity Field Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructor for YoastSeoManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Entity Type Bundle Info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field Manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string traslation service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityFieldManagerInterface $entityFieldManager, TranslationInterface $stringTranslation) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Returns an array of bundles that have a 'yoast_seo' field.
   *
   * @return array
   *   A nested array of entities and bundles. The outer array is keyed by
   *   entity id. The inner array is keyed by bundle id and contains the bundle
   *   label. If an entity has no bundles then the inner array is keyed by
   *   entity id.
   */
  public function getEnabledBundles() {
    $entities = [];

    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      $entity_id = $definition->id();
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_id);

      foreach ($bundles as $bundle_id => $bundle_metadata) {
        $bundle_label = $bundle_metadata['label'];
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_id, $bundle_id);

        if (!empty($field_definitions['yoast_seo'])) {
          if (!isset($entities[$entity_id])) {
            $entities[$entity_id] = [];
          }

          $entities[$entity_id][$bundle_id] = $bundle_label;
        }
      }
    }

    return $entities;
  }

  /**
   * Returns the Real-Time SEO field of the entity.
   *
   * Returns the first field of the entity that is a Real-Time SEO field or
   * null if none is found.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which to find the Real-Time SEO field.
   *
   * @return null|\Drupal\Core\Field\FieldItemListInterface
   *   The field item list of the field or NULL if no RTSEO field was found.
   */
  public function getSeoField(FieldableEntityInterface $entity) {
    $definitions = $entity->getFieldDefinitions();

    // Find the first yoast_seo field on the entity.
    foreach ($definitions as $definition) {
      if ($definition->getType() === 'yoast_seo') {
        return $entity->get($definition->getName());
      }
    }

    // No field of yoast_seo type was found.
    return NULL;

  }

  /**
   * Get the status for a given score.
   *
   * @todo Move this back to something like an SEO Assessor.
   *
   * @param int $score
   *   Score in points.
   *
   * @return string
   *   Status corresponding to the score.
   */
  public function getScoreStatus($score) {
    $rules = $this->getScoreRules();

    foreach ($rules as $minimum => $label) {
      // As soon as our score is bigger than a rules threshold, use that label.
      if ($score >= $minimum) {
        return $label;
      }
    }

    return $this->t('Unknown');
  }

  /**
   * Retrieves the score rules from configuration.
   *
   * @return string[]
   *   A list of labels indexed by the minimum score required. Ordered from high
   *   to low.
   */
  public function getScoreRules() {
    $rules = \Drupal::config('yoast_seo.settings')->get('score_rules') ?? [];

    // Ensure rules are sorted from high to low score.
    ksort($rules);
    return array_reverse($rules, TRUE);
  }

}
