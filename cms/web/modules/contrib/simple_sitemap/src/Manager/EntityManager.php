<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGeneratorBase;
use Drupal\simple_sitemap\Settings;

/**
 * The simple_sitemap.entity_manager service.
 */
class EntityManager implements SitemapGetterInterface {

  use SitemapGetterTrait;
  use LinkSettingsTrait;

  /**
   * Default link settings.
   *
   * @var array
   */
  protected static $linkSettingDefaults = [
    'index' => FALSE,
    'priority' => '0.5',
    'changefreq' => '',
    'include_images' => FALSE,
  ];

  /**
   * Helper class for working with entities.
   *
   * @var \Drupal\simple_sitemap\Entity\EntityHelper
   */
  protected $entityHelper;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Simplesitemap constructor.
   *
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    EntityHelper $entity_helper,
    Settings $settings,
    ConfigFactoryInterface $config_factory,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->entityHelper = $entity_helper;
    $this->settings = $settings;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Enables sitemap support for an entity type.
   *
   * Enabled entity types show
   * sitemap settings on their bundle setting forms. If an enabled entity type
   * features bundles (e.g. 'node'), it needs to be set up with
   * setBundleSettings() as well.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return $this
   */
  public function enableEntityType(string $entity_type_id): EntityManager {
    $enabled_entity_types = $this->settings->get('enabled_entity_types', []);

    if (!in_array($entity_type_id, $enabled_entity_types, TRUE)) {
      $enabled_entity_types[] = $entity_type_id;
      $this->settings->save('enabled_entity_types', $enabled_entity_types);

      // Clear necessary caches to apply field definition updates.
      // @see simple_sitemap_entity_extra_field_info()
      $this->entityFieldManager->clearCachedFieldDefinitions();
    }

    return $this;
  }

  /**
   * Disables sitemap support for an entity type.
   *
   * Disabling support for an entity type deletes its sitemap settings
   * permanently and removes sitemap settings from entity forms.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return $this
   */
  public function disableEntityType(string $entity_type_id): EntityManager {

    // Updating settings.
    $enabled_entity_types = $this->settings->get('enabled_entity_types', []);
    if (FALSE !== ($key = array_search($entity_type_id, $enabled_entity_types, TRUE))) {
      unset($enabled_entity_types[$key]);
      $this->settings->save('enabled_entity_types', array_values($enabled_entity_types));

      // Clear necessary caches to apply field definition updates.
      // @see simple_sitemap_entity_extra_field_info()
      $this->entityFieldManager->clearCachedFieldDefinitions();
    }

    // Deleting inclusion settings.
    foreach ($this->configFactory->listAll('simple_sitemap.bundle_settings.') as $config_name) {
      if (explode('.', $config_name)[3] === $entity_type_id) {
        $this->configFactory->getEditable($config_name)->delete();
      }
    }

    // @todo Implement hook to be used inside simple_sitemap_engines?
    // Deleting inclusion settings for simple_sitemap_engines.
    foreach ($this->configFactory->listAll('simple_sitemap_engines.bundle_settings.') as $config_name) {
      if (explode('.', $config_name)[2] === $entity_type_id) {
        $this->configFactory->getEditable($config_name)->delete();
      }
    }

    // Deleting entity overrides.
    $this->setSitemaps()->removeEntityInstanceSettings($entity_type_id);

    return $this;
  }

  /**
   * Sets settings for bundle or non-bundle entity types.
   *
   * This is done for the currently set variant. Note that this method takes
   * only the first set variant into account.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle_name
   *   The bundle of the entity.
   * @param array $settings
   *   Settings to set.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo Make work for multiple variants.
   * @todo Throw exception on non-existing entity type/bundle.
   */
  public function setBundleSettings(string $entity_type_id, ?string $bundle_name = NULL, array $settings = ['index' => TRUE]): EntityManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }
    if (!isset($this->entityHelper->getSupportedEntityTypes()[$entity_type_id])) {
      return $this;
    }

    // @todo Not working with menu link content.
    // phpcs:disable
//    if ($bundle_name && !isset($this->entityHelper->getBundleInfo($entity_type_id)[$bundle_name])) {
//      return $this;
//    }
    // phpcs:enable

    $bundle_name = $bundle_name ?? $entity_type_id;

    if ($old_settings = $this->getBundleSettings($entity_type_id, $bundle_name)) {
      $old_settings = reset($old_settings);
      $settings = array_merge($old_settings, $settings);
    }
    self::supplementDefaultSettings($settings);

    if ($settings != $old_settings) {

      // Save new bundle settings to configuration.
      $bundle_settings = $this->configFactory
        ->getEditable("simple_sitemap.bundle_settings.$variants[0].$entity_type_id.$bundle_name");
      foreach ($settings as $setting_key => $setting) {
        $bundle_settings->set($setting_key, $setting);
      }
      $bundle_settings->save();

      if (empty($entity_ids = $this->entityHelper->getEntityInstanceIds($entity_type_id, $bundle_name))) {
        return $this;
      }

      // Delete all entity overrides in case bundle indexation is disabled.
      if (empty($settings['index'])) {
        $this->removeEntityInstanceSettings($entity_type_id, $entity_ids);

        return $this;
      }

      // Delete entity overrides which are identical to new bundle settings.
      // @todo Enclose into some sensible method.
      $query = $this->database->select('simple_sitemap_entity_overrides', 'o')
        ->fields('o', ['id', 'inclusion_settings'])
        ->condition('o.entity_type', $entity_type_id)
        ->condition('o.type', $variants[0])
        ->condition('o.entity_id', $entity_ids, 'IN');

      $delete_instances = [];
      foreach ($query->execute()->fetchAll() as $result) {
        $delete = TRUE;
        $instance_settings = unserialize($result->inclusion_settings, ['allowed_classes' => FALSE]);
        foreach ($instance_settings as $setting_key => $instance_setting) {
          if ($instance_setting != $settings[$setting_key]) {
            $delete = FALSE;
            break;
          }
        }
        if ($delete) {
          $delete_instances[] = $result->id;
        }
      }
      if (!empty($delete_instances)) {

        // @todo Use removeEntityInstanceSettings() instead.
        $this->database->delete('simple_sitemap_entity_overrides')
          ->condition('id', $delete_instances, 'IN')
          ->execute();
      }
    }

    return $this;
  }

  /**
   * Gets sitemap settings for an entity type (bundle).
   *
   * This is done for the currently set variants.
   *
   * @param string $entity_type_id
   *   Limit the result set to a specific entity type.
   * @param string|null $bundle_name
   *   Limit the result set to a specific bundle name.
   *
   * @return array
   *   An array of settings keyed by variant name.
   *
   * @todo Throw exception on non-existing entity type/bundle.
   */
  public function getBundleSettings(string $entity_type_id, ?string $bundle_name = NULL): array {
    if (!isset($this->entityHelper->getSupportedEntityTypes()[$entity_type_id])) {
      return [];
    }
    // @todo Not working with menu link content.
    // phpcs:disable
//    if ($bundle_name && !isset($this->entityHelper->getBundleInfo($entity_type_id)[$bundle_name])) {
//      return [];
//    }
    // phpcs:enable
    $bundle_name = $bundle_name ?? $entity_type_id;
    $all_bundle_settings = [];

    foreach (array_keys($this->getSitemaps()) as $variant) {
      $bundle_settings = $this->configFactory
        ->get("simple_sitemap.bundle_settings.$variant.$entity_type_id.$bundle_name")
        ->get();

      if (empty($bundle_settings)) {
        self::supplementDefaultSettings($bundle_settings);
      }
      $all_bundle_settings[$variant] = $bundle_settings;
    }

    return $all_bundle_settings;
  }

  /**
   * Gets settings for all entity types (bundles).
   *
   * This is done for the currently set variants.
   *
   * @return array
   *   An array of settings keyed by variant name, entity type and bundle names.
   */
  public function getAllBundleSettings() {
    $all_bundle_settings = [];
    foreach (array_keys($this->getSitemaps()) as $variant) {
      $config_names = $this->configFactory->listAll("simple_sitemap.bundle_settings.$variant.");
      $bundle_settings = [];
      foreach ($config_names as $config_name) {
        $config_name_parts = explode('.', $config_name);
        $bundle_settings[$config_name_parts[3]][$config_name_parts[4]] = $this->configFactory->get($config_name)->get();
      }

      // Supplement default bundle settings for all bundles not found in
      // simple_sitemap.bundle_settings.*.* configuration.
      foreach ($this->entityHelper->getSupportedEntityTypes() as $type_id => $type_definition) {
        foreach ($this->entityHelper->getBundleInfo($type_id) as $bundle => $bundle_definition) {
          if (!isset($bundle_settings[$type_id][$bundle])) {
            self::supplementDefaultSettings($bundle_settings[$type_id][$bundle]);
          }
        }
      }
      $all_bundle_settings[$variant] = $bundle_settings;
    }

    return $all_bundle_settings;
  }

  /**
   * Removes settings for bundle or a non-bundle entity types.
   *
   * This is done for the currently set variants.
   *
   * @param string|null $entity_type_id
   *   Limit the removal to a specific entity type.
   * @param string|null $bundle_name
   *   Limit the removal to a specific bundle name.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function removeBundleSettings(?string $entity_type_id = NULL, ?string $bundle_name = NULL): EntityManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }

    if (NULL !== $entity_type_id) {
      $bundle_name = $bundle_name ?? $entity_type_id;

      foreach ($variants as $variant) {
        $this->configFactory
          ->getEditable("simple_sitemap.bundle_settings.$variant.$entity_type_id.$bundle_name")->delete();
      }

      if (!empty($entity_ids = $this->entityHelper->getEntityInstanceIds($entity_type_id, $bundle_name))) {
        $this->removeEntityInstanceSettings($entity_type_id, $entity_ids);
      }
    }
    else {
      foreach ($variants as $variant) {
        $config_names = $this->configFactory->listAll("simple_sitemap.bundle_settings.$variant.");
        foreach ($config_names as $config_name) {
          $this->configFactory->getEditable($config_name)->delete();
        }
      }
      $this->removeEntityInstanceSettings();
    }

    return $this;
  }

  /**
   * Overrides settings for a single entity for the currently set variants.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $id
   *   The entity identifier.
   * @param array $settings
   *   Settings to set.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo Pass entity object instead of id and entity type?
   */
  public function setEntityInstanceSettings(string $entity_type_id, string $id, array $settings): EntityManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }

    if (($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) === NULL) {
      // @todo Exception.
      return $this;
    }

    $all_bundle_settings = $this->getBundleSettings(
      $entity_type_id, $this->entityHelper->getEntityBundle($entity)
    );

    foreach ($all_bundle_settings as $variant => $bundle_settings) {
      if (!empty($bundle_settings)) {

        // Only one variant at a time.
        $this->setSitemaps($variant);

        // Check if overrides are different from bundle setting before saving.
        $override = FALSE;
        foreach ($settings as $key => $setting) {
          if (!isset($bundle_settings[$key]) || $setting != $bundle_settings[$key]) {
            $override = TRUE;
            break;
          }
        }

        // Save overrides for this entity if something is different.
        if ($override) {
          $this->database->merge('simple_sitemap_entity_overrides')
            ->keys([
              'type' => $variant,
              'entity_type' => $entity_type_id,
              'entity_id' => $id,
            ])
            ->fields([
              'type' => $variant,
              'entity_type' => $entity_type_id,
              'entity_id' => $id,
              'inclusion_settings' => serialize(array_merge($bundle_settings, $settings)),
            ])
            ->execute();
        }
        // Else unset override.
        else {
          $this->removeEntityInstanceSettings($entity_type_id, $id);
        }
      }
    }

    // Restore original variants.
    $this->setSitemaps($variants);

    return $this;
  }

  /**
   * Gets sitemap settings for an entity instance.
   *
   * If instance-specific setting overrides are not saved, returns bundle
   * settings. This is done for the currently set variant.
   * Please note, this method takes only the first set
   * variant into account.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $id
   *   The entity identifier.
   *
   * @return array|false
   *   Array of entity instance settings or the settings of its bundle. False if
   *   entity or variant does not exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo Make work for multiple variants.
   * @todo Pass entity object instead of id and entity type?
   */
  public function getEntityInstanceSettings(string $entity_type_id, string $id) {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return FALSE;
    }
    $variant = reset($variants);

    $results = $this->database->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.type', $variant)
      ->condition('o.entity_type', $entity_type_id)
      ->condition('o.entity_id', $id)
      ->execute()
      ->fetchField();

    if (!empty($results)) {
      return [$variant => unserialize($results, ['allowed_classes' => FALSE])];
    }

    if (($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) === NULL) {
      return FALSE;
    }

    $bundle_settings = $this->getBundleSettings(
      $entity_type_id,
      $this->entityHelper->getEntityBundle($entity)
    );

    return $bundle_settings ?: FALSE;
  }

  /**
   * Removes sitemap settings for entities that override bundle settings.
   *
   * This is done for the currently set variants.
   *
   * @param string|null $entity_type_id
   *   Limits the removal to a certain entity type.
   * @param string|array|null $entity_ids
   *   Limits the removal to entities with certain IDs.
   *
   * @return $this
   */
  public function removeEntityInstanceSettings(?string $entity_type_id = NULL, $entity_ids = NULL): EntityManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }

    $query = $this->database->delete('simple_sitemap_entity_overrides')
      ->condition('type', $variants, 'IN');

    if (NULL !== $entity_type_id) {
      $query->condition('entity_type', $entity_type_id);

      if (NULL !== $entity_ids) {
        $query->condition('entity_id', (array) $entity_ids, 'IN');
      }
    }

    $query->execute();

    return $this;
  }

  /**
   * Checks the index status for an entity bundle.
   *
   * Checks if an entity bundle (or a non-bundle entity type) is set to be
   * indexed for any of the currently set variants.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle_name
   *   The bundle of the entity.
   *
   * @return bool
   *   TRUE if an entity bundle is indexed, FALSE otherwise.
   */
  public function bundleIsIndexed(string $entity_type_id, ?string $bundle_name = NULL): bool {
    foreach ($this->getBundleSettings($entity_type_id, $bundle_name) as $bundle_settings) {
      if (!empty($bundle_settings['index'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks if an entity type is enabled in the sitemap settings.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if an entity type is enabled, FALSE otherwise.
   */
  public function entityTypeIsEnabled(string $entity_type_id): bool {
    return in_array($entity_type_id, $this->settings->get('enabled_entity_types', []), TRUE);
  }

  /**
   * Gets all compatible sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   Array of sitemaps of a type that uses a URL generator which
   *   extends EntityUrlGeneratorBase. Keyed by variant.
   *
   * @todo This is not ideal as it shows only-menu-link-sitemaps on all bundle
   * setting pages and vice versa. It also shows only-custom-link-sitemaps on
   * all bundle setting pages and vice versa.
   */
  protected function getCompatibleSitemaps(): array {
    foreach (SimpleSitemap::loadMultiple() as $variant => $sitemap) {
      foreach ($sitemap->getType()->getUrlGenerators() as $url_generator) {
        if ($url_generator instanceof EntityUrlGeneratorBase) {
          $sitemaps[$variant] = $sitemap;
          break;
        }
      }
    }

    return $sitemaps ?? [];
  }

}
