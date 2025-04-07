<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simple_sitemap\Exception\SitemapNotExistsException;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storage handler for sitemap configuration entities.
 */
class SimpleSitemapStorage extends ConfigEntityStorage {

  public const SITEMAP_INDEX_DELTA = 0;
  public const SITEMAP_CHUNK_FIRST_DELTA = 1;

  protected const SITEMAP_PUBLISHED = 1;
  protected const SITEMAP_UNPUBLISHED = 0;

  /**
   * The database connection to be used.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * SimpleSitemapStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, Connection $database, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, Settings $settings) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
    $this->database = $database;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.settings')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo Improve performance of this method.
   */
  protected function doDelete($entities) {
    $default_variant = $this->settings->get('default_variant');

    /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[] $entities */
    foreach ($entities as $entity) {

      // Remove sitemap content.
      $this->deleteContent($entity);

      // Unset default variant setting if necessary.
      if ($default_variant === $entity->id()) {
        $this->settings->save('default_variant', NULL);
      }

      // Remove bundle settings.
      foreach ($this->configFactory->listAll("simple_sitemap.bundle_settings.{$entity->id()}.") as $config_name) {
        $this->configFactory->getEditable($config_name)->delete();
      }

      // Remove custom links.
      foreach ($this->configFactory->listAll("simple_sitemap.custom_links.{$entity->id()}") as $config_name) {
        $this->configFactory->getEditable($config_name)->delete();
      }

      // Remove bundle settings entity overrides.
      $this->database->delete('simple_sitemap_entity_overrides')->condition('type', $entity->id())->execute();
    }

    parent::doDelete($entities);
  }

  /**
   * Loads all sitemaps, sorted by their weight.
   *
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL): array {
    $sitemaps = parent::doLoadMultiple($ids);
    uasort($sitemaps, [SimpleSitemap::class, 'sort']);

    return $sitemaps;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []): array {
    $sitemaps = parent::loadByProperties($values);
    uasort($sitemaps, [SimpleSitemap::class, 'sort']);

    return $sitemaps;
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $values = []) {
    if (isset($values['id']) && ($sitemap = SimpleSitemap::load($values['id'])) !== NULL) {
      foreach (['type', 'label', 'weight'] as $property) {
        if (isset($values[$property])) {
          $sitemap->set('type', $values[$property]);
        }
      }
      return $sitemap;
    }

    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var SimpleSitemapInterface $entity */
    if (preg_match('/[^a-z0-9-_]+/', $id)) {
      throw new \InvalidArgumentException('The sitemap ID can only contain lowercase letters, numbers, dashes and underscores.');
    }

    if ($entity->get('type') === NULL || $entity->get('type') === '') {
      throw new \InvalidArgumentException('The sitemap must define its sitemap type information.');
    }

    if ($this->entityTypeManager->getStorage('simple_sitemap_type')->load($entity->get('type')) === NULL) {
      throw new \InvalidArgumentException("Sitemap type {$entity->get('type')} does not exist.");
    }

    if ($entity->label() === NULL || $entity->label() === '') {
      $entity->set('label', $id);
    }

    if ($entity->get('weight') === NULL || $entity->get('weight') === '') {
      $entity->set('weight', 0);
    }

    // If disabling the entity, delete sitemap content if any.
    if (!$entity->isEnabled() && $entity->fromPublishedAndUnpublished()->getChunkCount()) {
      $this->deleteContent($entity);
    }
    // We need the else since we don't want to thrash cache invalidation and
    // deleting content already invalidates cache.
    else {
      $this->invalidateCache([$entity->id()]);
    }

    return parent::doSave($id, $entity);
  }

  /**
   * Retrieves the chunk data for the specified sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   *
   * @return array
   *   The chunk data.
   *
   * @todo Costs too much.
   */
  protected function getChunkData(SimpleSitemapInterface $entity) {
    return $this->database->select('simple_sitemap', 's')
      ->fields('s', [
        'id',
        'type',
        'delta',
        'sitemap_created',
        'status',
        'link_count',
      ])
      ->condition('s.type', $entity->id())
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Publishes the specified sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity to publish.
   */
  public function publish(SimpleSitemapInterface $entity): void {
    $unpublished_chunk = $this->database->query('SELECT MAX(id) FROM {simple_sitemap} WHERE type = :type AND status = :status', [
      ':type' => $entity->id(),
      ':status' => self::SITEMAP_UNPUBLISHED,
    ])->fetchField();

    // Only allow publishing a sitemap variant if there is an unpublished
    // sitemap variant, as publishing involves deleting the currently published
    // variant.
    if (FALSE !== $unpublished_chunk) {
      $this->database->delete('simple_sitemap')->condition('type', $entity->id())->condition('status', self::SITEMAP_PUBLISHED)->execute();
      $this->database->query('UPDATE {simple_sitemap} SET status = :status WHERE type = :type', [
        ':type' => $entity->id(),
        ':status' => self::SITEMAP_PUBLISHED,
      ]);
      $this->invalidateCache([$entity->id()]);
    }
  }

  /**
   * Removes the content of the specified sitemap.
   *
   * A sitemap entity can exist without the sitemap (XML) content which lives
   * in the DB. This purges the sitemap content.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity to process.
   */
  public function deleteContent(SimpleSitemapInterface $entity): void {
    $this->purgeContent([$entity->id()]);
  }

  /**
   * Adds a new content chunk to the specified sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity to process.
   * @param string $content
   *   The sitemap chunk content.
   * @param int $link_count
   *   Number of links.
   *
   * @throws \Exception
   */
  public function addChunk(SimpleSitemapInterface $entity, string $content, $link_count): void {
    $highest_delta = $this->database->query('SELECT MAX(delta) FROM {simple_sitemap} WHERE type = :type AND status = :status', [
      ':type' => $entity->id(),
      ':status' => self::SITEMAP_UNPUBLISHED,
    ])
      ->fetchField();

    $this->database->insert('simple_sitemap')->fields([
      'delta' => NULL === $highest_delta ? self::SITEMAP_CHUNK_FIRST_DELTA : $highest_delta + 1,
      'type' => $entity->id(),
      'sitemap_string' => $content,
      'sitemap_created' => $this->time->getRequestTime(),
      'status' => 0,
      'link_count' => $link_count,
    ])->execute();
    $this->invalidateCache([$entity->id()]);
  }

  /**
   * Generates the chunk index of the specified sitemap's content chunks.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity to process.
   * @param string $content
   *   The sitemap index content.
   *
   * @throws \Exception
   */
  public function generateIndex(SimpleSitemapInterface $entity, string $content): void {
    $this->database->merge('simple_sitemap')
      ->keys([
        'delta' => self::SITEMAP_INDEX_DELTA,
        'type' => $entity->id(),
        'status' => 0,
      ])
      ->insertFields([
        'delta' => self::SITEMAP_INDEX_DELTA,
        'type' => $entity->id(),
        'sitemap_string' => $content,
        'sitemap_created' => $this->time->getRequestTime(),
        'status' => 0,
      ])
      ->updateFields([
        'sitemap_string' => $content,
        'sitemap_created' => $this->time->getRequestTime(),
      ])
      ->execute();
    $this->invalidateCache([$entity->id()]);
  }

  /**
   * Returns the number of all content chunks of the specified sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return int
   *   Number of chunks.
   */
  public function getChunkCount(SimpleSitemapInterface $entity, ?int $status = SimpleSitemap::FETCH_BY_STATUS_ALL): int {
    $query = $this->database->select('simple_sitemap', 's')
      ->condition('s.type', $entity->id())
      ->condition('s.delta', self::SITEMAP_INDEX_DELTA, '<>');

    if ($status !== SimpleSitemap::FETCH_BY_STATUS_ALL) {
      $query->condition('s.status', $status);
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Retrieves the content of a specified sitemap's chunk.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int|null $status
   *   Fetch by sitemap status.
   * @param int $delta
   *   Delta of the chunk.
   *
   * @return string
   *   The sitemap chunk content.
   *
   * @todo Fix the duplicate query.
   */
  public function getChunk(SimpleSitemapInterface $entity, ?int $status, int $delta = SimpleSitemapStorage::SITEMAP_CHUNK_FIRST_DELTA): string {
    if ($delta === self::SITEMAP_INDEX_DELTA) {
      throw new SitemapNotExistsException('The sitemap chunk delta cannot be ' . self::SITEMAP_INDEX_DELTA . '.');
    }

    return $this->getSitemapString($entity, $this->getIdByDelta($entity, $delta, $status), $status);
  }

  /**
   * Determines whether the specified sitemap has a chunk index.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity to check.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return bool
   *   TRUE if the sitemap has an index, FALSE otherwise.
   */
  public function hasIndex(SimpleSitemapInterface $entity, ?int $status): bool {
    try {
      $this->getIdByDelta($entity, self::SITEMAP_INDEX_DELTA, $status);
      return TRUE;
    }
    catch (SitemapNotExistsException $e) {
      return FALSE;
    }
  }

  /**
   * Gets the sitemap chunk index content.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return string
   *   The sitemap index content.
   *
   * @todo Fix the duplicate query.
   */
  public function getIndex(SimpleSitemapInterface $entity, ?int $status): string {
    return $this->getSitemapString($entity, $this->getIdByDelta($entity, self::SITEMAP_INDEX_DELTA, $status), $status);
  }

  /**
   * Returns the sitemap chunk ID by delta.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int $delta
   *   Delta of the chunk.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return int
   *   The sitemap chunk ID.
   */
  protected function getIdByDelta(SimpleSitemapInterface $entity, int $delta, ?int $status): int {
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($chunk->delta == $delta && $chunk->status == $status) {
        return $chunk->id;
      }
    }

    throw new SitemapNotExistsException();
  }

  /**
   * Retrieves the sitemap chunk content.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int $id
   *   The sitemap chunk ID.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return string
   *   The sitemap chunk content.
   */
  protected function getSitemapString(SimpleSitemapInterface $entity, int $id, ?int $status): string {
    $chunk_data = $this->getChunkData($entity);
    if (!isset($chunk_data[$id])) {
      throw new SitemapNotExistsException();
    }

    if (empty($chunk_data[$id]->sitemap_string)) {
      $query = $this->database->select('simple_sitemap', 's')
        ->fields('s', ['sitemap_string'])
        ->condition('status', $status)
        ->condition('id', $id);

      $chunk_data[$id]->sitemap_string = $query->execute()->fetchField();
    }

    return $chunk_data[$id]->sitemap_string;
  }

  /**
   * Returns the status of the specified sitemap.
   *
   * The sitemap can be unpublished (0), published (1), or published and in
   * regeneration (2).
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   *
   * @return int
   *   The sitemap status.
   */
  public function status(SimpleSitemapInterface $entity): int {
    foreach ($this->getChunkData($entity) as $chunk) {
      $status[$chunk->status] = $chunk->status;
    }

    if (!isset($status)) {
      return SimpleSitemap::SITEMAP_UNPUBLISHED;
    }

    if (count($status) === 1) {
      return (int) reset($status) === self::SITEMAP_UNPUBLISHED
        ? SimpleSitemap::SITEMAP_UNPUBLISHED
        : SimpleSitemap::SITEMAP_PUBLISHED;
    }

    return SimpleSitemap::SITEMAP_PUBLISHED_GENERATING;
  }

  /**
   * Returns the timestamp of the specified sitemap's chunk generation.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return int|null
   *   Timestamp of sitemap chunk generation.
   */
  public function getCreated(SimpleSitemapInterface $entity, ?int $status = SimpleSitemap::FETCH_BY_STATUS_ALL): ?int {
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($status === SimpleSitemap::FETCH_BY_STATUS_ALL || $chunk->status == $status) {
        return $chunk->sitemap_created;
      }
    }

    return NULL;
  }

  /**
   * Returns the number of links indexed in the specified sitemap's content.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The sitemap entity.
   * @param int|null $status
   *   Fetch by sitemap status.
   *
   * @return int
   *   Number of links.
   */
  public function getLinkCount(SimpleSitemapInterface $entity, ?int $status = SimpleSitemap::FETCH_BY_STATUS_ALL): int {
    $count = 0;
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($chunk->delta != self::SITEMAP_INDEX_DELTA
        && ($status === SimpleSitemap::FETCH_BY_STATUS_ALL || $chunk->status == $status)) {
        $count += (int) $chunk->link_count;
      }
    }

    return $count;
  }

  /**
   * Removes the content from all or specified sitemaps.
   *
   * A sitemap entity can exist without the sitemap (XML) content which lives
   * in the DB. This purges the sitemap content.
   *
   * @param array|null $variants
   *   An array of sitemap IDs, or NULL for all sitemaps.
   * @param int|null $status
   *   Purge by sitemap status.
   */
  public function purgeContent(?array $variants = NULL, ?int $status = SimpleSitemap::FETCH_BY_STATUS_ALL): void {
    $query = $this->database->delete('simple_sitemap');
    if ($status !== SimpleSitemap::FETCH_BY_STATUS_ALL) {
      $query->condition('status', $status);
    }
    if ($variants !== NULL) {
      $query->condition('type', $variants, 'IN');
    }
    $query->execute();
    $this->invalidateCache($variants);
  }

  /**
   * Invalidates cache for all or specified sitemaps.
   *
   * @param array|null $variants
   *   An array of sitemap IDs, or NULL for all sitemaps.
   */
  public function invalidateCache(?array $variants = NULL): void {
    $variants = $variants ?? array_keys(SimpleSitemap::loadMultiple());
    $tags = Cache::buildTags('simple_sitemap', $variants);
    Cache::invalidateTags($tags);
  }

}
