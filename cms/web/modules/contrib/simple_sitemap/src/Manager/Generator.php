<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\simple_sitemap\Settings;

/**
 * Main managing service.
 *
 * Capable of setting/loading module settings, queueing elements and generating
 * the sitemap. Services for custom link and entity link generation can be
 * fetched from this service as well.
 */
class Generator implements SitemapGetterInterface {

  use SitemapGetterTrait;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * The simple_sitemap.queue_worker service.
   *
   * @var \Drupal\simple_sitemap\Queue\QueueWorker
   */
  protected $queueWorker;

  /**
   * The lock backend that should be used.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Simple XML Sitemap logger.
   *
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * Simplesitemap constructor.
   *
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker
   *   The simple_sitemap.queue_worker service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend that should be used.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   Simple XML Sitemap logger.
   */
  public function __construct(
    Settings $settings,
    QueueWorker $queue_worker,
    LockBackendInterface $lock,
    Logger $logger,
  ) {
    $this->settings = $settings;
    $this->queueWorker = $queue_worker;
    $this->lock = $lock;
    $this->logger = $logger;
  }

  /**
   * Returns a specific setting or a default value if setting does not exist.
   *
   * @param string $name
   *   Name of the setting, like 'max_links'.
   * @param mixed $default
   *   Value to be returned if the setting does not exist in the configuration.
   *
   * @return mixed
   *   The current setting from configuration or a default value.
   */
  public function getSetting(string $name, $default = NULL) {
    return $this->settings->get($name, $default);
  }

  /**
   * Stores a specific sitemap setting in configuration.
   *
   * @param string $name
   *   Setting name, like 'max_links'.
   * @param mixed $setting
   *   The setting to be saved.
   *
   * @return $this
   */
  public function saveSetting(string $name, $setting): Generator {
    $this->settings->save($name, $setting);

    return $this;
  }

  /**
   * Gets the default variant from the currently set variants.
   *
   * @return string|null
   *   The default variant or NULL if there are no variants.
   *
   * @deprecated in simple_sitemap:4.1.7 and is removed from simple_sitemap:5.0.0.
   *   Use getDefaultSitemap() instead.
   * @see https://www.drupal.org/project/simple_sitemap/issues/3375932
   */
  public function getDefaultVariant(): ?string {
    return ($defaultSitemap = $this->getDefaultSitemap()) ? $defaultSitemap->id() : NULL;
  }

  /**
   * Gets the default sitemap from the currently set sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface|null
   *   The default sitemap or NULL if there are no sitemaps.
   */
  public function getDefaultSitemap(): ?SimpleSitemapInterface {
    if (empty($sitemaps = $this->getSitemaps())) {
      return NULL;
    }

    if (count($sitemaps) > 1) {
      $variant = $this->getSetting('default_variant');

      if ($variant && array_key_exists($variant, $sitemaps)) {
        return $sitemaps[$variant];
      }
    }

    return reset($sitemaps);
  }

  /**
   * Returns a sitemap variant, its index, or its requested chunk.
   *
   * @param int|null $delta
   *   Optional delta of the chunk.
   *
   * @return string|null
   *   If no chunk delta is provided, either the sitemap string is returned,
   *   or its index string in case of a chunked sitemap.
   *   If a chunk delta is provided, the relevant chunk string is returned.
   *   Returns null if the content is not retrievable from the database.
   */
  public function getContent(?int $delta = NULL): ?string {
    $sitemap = $this->getDefaultSitemap();

    if ($sitemap
      && $sitemap->isEnabled()
      && ($sitemap_string = $sitemap->fromPublished()->toString($delta))) {
      return $sitemap_string;
    }

    return NULL;
  }

  /**
   * Generates all sitemaps.
   *
   * @param string $from
   *   Can be 'form', 'drush', 'cron' and 'backend'.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function generate(string $from = QueueWorker::GENERATE_TYPE_FORM): Generator {
    if (!$this->lock->lockMayBeAvailable(QueueWorker::LOCK_ID)) {
      $this->logger->m('Unable to acquire a lock for sitemap generation.')->log('error')->display('error');
      return $this;
    }
    switch ($from) {
      case QueueWorker::GENERATE_TYPE_FORM:
      case QueueWorker::GENERATE_TYPE_DRUSH;
        $this->queueWorker->batchGenerate($from);
        break;

      case QueueWorker::GENERATE_TYPE_CRON:
      case QueueWorker::GENERATE_TYPE_BACKEND:
        $this->queueWorker->generate($from);
        break;
    }

    return $this;
  }

  /**
   * Queues links from currently set variants.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function queue(): Generator {
    $this->queueWorker->queue($this->getSitemaps());

    return $this;
  }

  /**
   * Deletes the queue and queues links from currently set variants.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function rebuildQueue(): Generator {
    if (!$this->lock->lockMayBeAvailable(QueueWorker::LOCK_ID)) {
      $this->logger->m('Unable to acquire a lock for sitemap generation.')->log('error')->display('error');
      return $this;
    }
    $this->queueWorker->rebuildQueue($this->getSitemaps());

    return $this;
  }

  /**
   * Gets the simple_sitemap.entity_manager service.
   *
   * @return \Drupal\simple_sitemap\Manager\EntityManager
   *   The simple_sitemap.entity_manager service.
   */
  public function entityManager(): EntityManager {
    /** @var \Drupal\simple_sitemap\Manager\EntityManager $entity_manager */
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $entity_manager = \Drupal::service('simple_sitemap.entity_manager');

    if ($this->sitemaps !== NULL) {
      $entity_manager->setSitemaps($this->getSitemaps());
    }

    return $entity_manager;
  }

  /**
   * Gets the simple_sitemap.custom_link_manager service.
   *
   * @return \Drupal\simple_sitemap\Manager\CustomLinkManager
   *   The simple_sitemap.custom_link_manager service.
   */
  public function customLinkManager(): CustomLinkManager {
    /** @var \Drupal\simple_sitemap\Manager\CustomLinkManager $custom_link_manager */
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $custom_link_manager = \Drupal::service('simple_sitemap.custom_link_manager');

    if ($this->sitemaps !== NULL) {
      $custom_link_manager->setSitemaps($this->getSitemaps());
    }

    return $custom_link_manager;
  }

  /**
   * Gets all compatible sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   Array of sitemaps.
   */
  protected function getCompatibleSitemaps(): array {
    return SimpleSitemap::loadMultiple();
  }

}
