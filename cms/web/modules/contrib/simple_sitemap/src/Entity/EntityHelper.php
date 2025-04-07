<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\system\Entity\Menu;

/**
 * Helper class for working with entities.
 */
class EntityHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static cache of bundle information.
   *
   * @var array
   */
  protected $bundleInfo = [];

  /**
   * EntityHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The bundle info service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ConfigFactoryInterface $configFactory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->configFactory = $configFactory;
  }

  /**
   * Gets the bundle info of an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   An array of bundle information.
   */
  public function getBundleInfo(string $entity_type_id): array {
    if (!isset($this->bundleInfo[$entity_type_id])) {
      $bundle_info = &$this->bundleInfo[$entity_type_id];

      // Menu fix.
      if ($entity_type_id === 'menu_link_content') {
        $bundle_info = [];

        // phpcs:ignore DrupalPractice.Objects.GlobalClass.GlobalClass
        foreach (Menu::loadMultiple() as $menu) {
          $bundle_info[$menu->id()]['label'] = $menu->label();
        }
      }
      else {
        $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      }

      // Sort bundles by label.
      uasort($bundle_info, function ($a, $b) {
        return SortArray::sortByKeyString($a, $b, 'label');
      });
    }
    return $this->bundleInfo[$entity_type_id];
  }

  /**
   * Gets the label for the bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_name
   *   The entity bundle.
   *
   * @return string
   *   The bundle label.
   */
  public function getBundleLabel(string $entity_type_id, string $bundle_name) {
    return $this->getBundleInfo($entity_type_id)[$bundle_name]['label'] ?? $bundle_name;
  }

  /**
   * Gets the bundle of the entity.
   *
   * Special handling of 'menu_link_content' entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the bundle for.
   *
   * @return string
   *   The bundle of the entity.
   */
  public function getEntityBundle(EntityInterface $entity): string {
    return $entity->getEntityTypeId() === 'menu_link_content' && method_exists($entity, 'getMenuName') ? $entity->getMenuName() : $entity->bundle();
  }

  /**
   * Gets the entity type for which the entity provides bundles.
   *
   * Special handling of 'menu' entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the "bundle of" for.
   *
   * @return null|string
   *   The entity type for which the entity provides bundles, or NULL if does
   *   not provide bundles for another entity type.
   */
  public function getEntityBundleOf(EntityInterface $entity): ?string {
    return $entity->getEntityTypeId() === 'menu' ? 'menu_link_content' : $entity->getEntityType()->getBundleOf();
  }

  /**
   * Returns objects of entity types that can be indexed.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   *   Objects of entity types that can be indexed by the sitemap.
   */
  public function getSupportedEntityTypes(): array {
    return array_filter($this->entityTypeManager->getDefinitions(), [
      $this,
      'supports',
    ]);
  }

  /**
   * Determines if an entity type is supported or not.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return bool
   *   TRUE if entity type is supported, FALSE if not.
   */
  public function supports(EntityTypeInterface $entity_type): bool {
    return $entity_type instanceof ContentEntityTypeInterface && $entity_type->hasLinkTemplate('canonical');
  }

  /**
   * Checks whether an entity type does not provide bundles.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the entity type is atomic and FALSE otherwise.
   */
  public function entityTypeIsAtomic(string $entity_type_id): bool {

    // Menu fix.
    if ($entity_type_id === 'menu_link_content') {
      return FALSE;
    }

    $entity_types = $this->entityTypeManager->getDefinitions();

    if (!isset($entity_types[$entity_type_id])) {
      throw new \InvalidArgumentException("Entity type $entity_type_id does not exist.");
    }

    return empty($entity_types[$entity_type_id]->getBundleEntityType());
  }

  /**
   * Gets the entity from URL object.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityFromUrlObject(Url $url): ?EntityInterface {
    if ($url->isRouted()) {

      // Fix for the homepage, see
      // https://www.drupal.org/project/simple_sitemap/issues/3194130.
      if ($url->getRouteName() === '<front>' &&
        !empty($uri = $this->configFactory->get('system.site')->get('page.front'))) {
        $url = Url::fromUri('internal:' . $uri);
      }

      foreach ($url->getRouteParameters() as $entity_type_id => $entity_id) {
        if ($entity_id && $this->entityTypeManager->hasDefinition($entity_type_id)
          && $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id)) {
          return $entity;
        }
      }
    }

    return NULL;
  }

  /**
   * Gets the entity IDs by entity type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle_name
   *   The bundle name.
   *
   * @return array
   *   An array of entity IDs
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityInstanceIds(string $entity_type_id, ?string $bundle_name = NULL): array {
    $sitemap_entity_types = $this->getSupportedEntityTypes();
    if (!isset($sitemap_entity_types[$entity_type_id])) {
      return [];
    }

    $entity_query = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(TRUE);
    if ($bundle_name !== NULL && !$this->entityTypeIsAtomic($entity_type_id)) {
      $keys = $sitemap_entity_types[$entity_type_id]->getKeys();

      // Menu fix.
      $keys['bundle'] = $entity_type_id === 'menu_link_content' ? 'menu_name' : $keys['bundle'];

      $entity_query->condition($keys['bundle'], $bundle_name);
    }

    return $entity_query->execute();
  }

}
