<?php

namespace Drupal\eca\Service;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service class to handle types and bundles fields.
 */
class ContentEntityTypes {

  /**
   * String used in select list for entity type and bundles for "all" items.
   *
   * @var string
   */
  public const ALL = '_all';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type and bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Array containing all entity types and their bundles.
   *
   * @var array|null
   */
  private ?array $typesAndBundles = NULL;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
  }

  /**
   * Determines if the selected $type matches the type and bundle of $entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity which will be verified.
   * @param string $type
   *   The $type string as being selected from a modeller field prepared by
   *   the getTypesAndBundles() method below.
   *
   * @return bool
   *   TRUE is the given entity matches the selected type and bundle, including
   *   all the various "any" options globally or per entity type.
   */
  public function bundleFieldApplies(EntityInterface $entity, string $type): bool {
    return $this->bundleFieldAppliesForEntityTypeAndBundle($entity->getEntityTypeId(), $entity->bundle(), $type);
  }

  /**
   * Determines if the selected $type matches the given type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The entity bundle.
   * @param string $type
   *   The $type string as being selected from a modeller field prepared by
   *   the getTypesAndBundles() method below.
   *
   * @return bool
   *   TRUE is the given entity matches the selected type and bundle, including
   *   all the various "any" options globally or per entity type.
   */
  public function bundleFieldAppliesForEntityTypeAndBundle(string $entity_type_id, string $bundle, string $type): bool {
    if ($type === self::ALL) {
      return TRUE;
    }
    [$configured_type_id, $configured_bundle] = explode(' ', $type);
    if ($configured_bundle === self::ALL) {
      return $entity_type_id === $configured_type_id;
    }
    return $entity_type_id === $configured_type_id && $bundle === $configured_bundle;
  }

  /**
   * Gets the types and bundles or an empty array.
   *
   * @param bool $include_any
   *   If set to TRUE, the field template will contain an option to select
   *   any content type and bundle. Defaults to FALSE, where this options will
   *   be missing.
   * @param bool $include_bundles_any
   *   If set to TRUE, entity types may be selected without specifying a certain
   *   bundle. Defaults to TRUE.
   *
   * @return array
   *   The types and bundles.
   */
  public function getTypesAndBundles(bool $include_any = FALSE, bool $include_bundles_any = TRUE): array {
    $idx1 = 'any_' . (int) $include_any;
    $idx2 = 'bundle_any_' . (int) $include_bundles_any;
    if (!isset($this->typesAndBundles[$idx1][$idx2])) {
      $this->typesAndBundles[$idx1][$idx2] = $this->doGetTypesAndBundles($include_any, $include_bundles_any);
    }
    return $this->typesAndBundles[$idx1][$idx2];
  }

  /**
   * Gets the entity types.
   *
   * @return array
   *   The entity types.
   */
  public function getTypes(): array {
    $result = [];
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $result[$definition->id()] = $definition->getLabel();
      }
    }
    return $result;
  }

  /**
   * Gets the bundles of an entity type.
   *
   * @return array
   *   The bundles of the entity type.
   */
  public function getBundles(string $entity_type_id): array {
    $result = [];
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach ($bundles as $bundle => $bundleDef) {
      $result[$bundle] = $bundleDef['label'];
    }
    return $result;
  }

  /**
   * Gets the type and bundles.
   *
   * @param bool $include_any
   *   If set to TRUE, the field template will contain an option to select
   *   any content type and bundle. Defaults to FALSE, where this options will
   *   be missing.
   * @param bool $include_bundles_any
   *   If set to TRUE, entity types may be selected without specifying a certain
   *   bundle. Defaults to TRUE.
   *
   * @return array
   *   The type and bundles.
   */
  private function doGetTypesAndBundles(bool $include_any, bool $include_bundles_any): array {
    $result = [];
    if ($include_any) {
      $result[self::ALL] = '- any -';
    }
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        if ($include_bundles_any) {
          $result[$definition->id() . ' ' . self::ALL] = $definition->getLabel() . ': - any -';
        }
        $entity_keys = $definition->get('entity_keys');
        if (isset($entity_keys['bundle']) || !$include_bundles_any) {
          $bundles = $this->entityTypeBundleInfo->getBundleInfo($definition->id());
          foreach ($bundles as $bundle => $bundleDef) {
            $result[$definition->id() . ' ' . $bundle] = $definition->getLabel() . ': ' . $bundleDef['label'];
          }
        }
      }
    }
    asort($result);
    return $result;
  }

}
