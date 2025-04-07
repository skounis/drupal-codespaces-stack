<?php

namespace Drupal\simple_sitemap\Queue;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Settings;

/**
 * The simple_sitemap.queue_worker service.
 */
class QueueWorker {

  use BatchTrait;

  protected const REBUILD_QUEUE_CHUNK_ITEM_SIZE = 5000;
  public const LOCK_ID = 'simple_sitemap:generation';
  public const GENERATE_LOCK_TIMEOUT = 3600;

  public const GENERATE_TYPE_FORM = 'form';
  public const GENERATE_TYPE_DRUSH = 'drush';
  public const GENERATE_TYPE_CRON = 'cron';
  public const GENERATE_TYPE_BACKEND = 'backend';

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * The key/value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $store;

  /**
   * Simple XML Sitemap queue handler.
   *
   * @var \Drupal\simple_sitemap\Queue\SimpleSitemapQueue
   */
  protected $queue;

  /**
   * Simple XML Sitemap logger.
   *
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The lock backend that should be used.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The sitemap entity.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface|null
   */
  protected $sitemapProcessedNow;

  /**
   * The local cache of results.
   *
   * @var array
   */
  protected $results = [];

  /**
   * The local cache of processed results.
   *
   * @var array
   */
  protected $processedResults = [];

  /**
   * The local cache of processed paths.
   *
   * @var array
   */
  protected $processedPaths = [];

  /**
   * Sitemap generator settings.
   *
   * @var array
   */
  protected $generatorSettings;

  /**
   * Maximum links in a sitemap.
   *
   * @var int|null
   */
  protected $maxLinks;

  /**
   * The number of remaining elements.
   *
   * @var int|null
   */
  protected $elementsRemaining;

  /**
   * The initial number of queue items.
   *
   * @var int|null
   */
  protected $elementsTotal;

  /**
   * QueueWorker constructor.
   *
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value
   *   The Key/Value factory service.
   * @param \Drupal\simple_sitemap\Queue\SimpleSitemapQueue $element_queue
   *   Simple XML Sitemap queue handler.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   Simple XML Sitemap logger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend that should be used.
   */
  public function __construct(
    Settings $settings,
    KeyValueFactoryInterface $key_value,
    SimpleSitemapQueue $element_queue,
    Logger $logger,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    LockBackendInterface $lock,
  ) {
    $this->settings = $settings;
    $this->store = $key_value->get('simple_sitemap');
    $this->queue = $element_queue;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->lock = $lock;
  }

  /**
   * Queues links from sitemaps.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[] $sitemaps
   *   The sitemaps.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function queue($sitemaps = []): QueueWorker {
    $empty_variants = array_fill_keys(array_keys($sitemaps), TRUE);
    $all_data_sets = [];

    foreach ($sitemaps as $variant => $sitemap) {
      if ($sitemap->isEnabled()) {
        foreach ($sitemap->getType()->getUrlGenerators() as $url_generator_id => $url_generator) {
          // @todo Automatically set sitemap.
          foreach ($url_generator->setSitemap($sitemap)->getDataSets() as $data_set) {
            unset($empty_variants[$variant]);
            $all_data_sets[] = [
              'data' => $data_set,
              'sitemap' => $variant,
              'url_generator' => $url_generator_id,
            ];

            if (count($all_data_sets) === self::REBUILD_QUEUE_CHUNK_ITEM_SIZE) {
              $this->queueElements($all_data_sets);
              $all_data_sets = [];
            }
          }
        }
      }
    }

    if (!empty($all_data_sets)) {
      $this->queueElements($all_data_sets);
    }
    $this->getQueuedElementCount(TRUE);

    // Remove all sitemap content of variants which did not yield any queue
    // elements.
    foreach ($empty_variants as $variant => $is_empty) {
      $sitemaps[$variant]->deleteContent();
    }

    return $this;
  }

  /**
   * Deletes the queue and queues links from sitemaps.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[] $sitemaps
   *   The sitemaps.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function rebuildQueue($sitemaps = []): QueueWorker {
    if (!$this->lock->acquire(static::LOCK_ID)) {
      throw new \RuntimeException('Unable to acquire a lock for sitemap queue rebuilding');
    }
    $this->deleteQueue();
    $this->queue($sitemaps);
    $this->lock->release(static::LOCK_ID);

    return $this;
  }

  /**
   * Stores items to the queue.
   *
   * @param mixed $elements
   *   Datasets to process.
   *
   * @throws \Exception
   */
  protected function queueElements($elements): void {
    $this->queue->createItems($elements);
    $this->store->set('queue_items_initial_amount', ($this->store->get('queue_items_initial_amount') + count($elements)));
  }

  /**
   * Generates all sitemaps.
   *
   * @param string $from
   *   The source of generation.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo Use exception handling when skipping queue items.
   */
  public function generate(string $from = self::GENERATE_TYPE_FORM): QueueWorker {

    $this->generatorSettings = [
      'base_url' => $this->settings->get('base_url', ''),
      'xsl' => $this->settings->get('xsl', TRUE),
      'default_variant' => $this->settings->get('default_variant'),
      'skip_untranslated' => $this->settings->get('skip_untranslated', FALSE),
      'remove_duplicates' => $this->settings->get('remove_duplicates', TRUE),
      'excluded_languages' => $this->settings->get('excluded_languages', []),
    ];
    $this->maxLinks = $this->settings->get('max_links', 2000);
    $max_execution_time = $this->settings->get('generate_duration', 10000);
    Timer::start('simple_sitemap_generator');

    $this->unstashResults();

    if (!$this->generationInProgress()) {
      $this->rebuildQueue(SimpleSitemap::loadMultiple());
    }

    // Acquire a lock for max execution time + 5 seconds. If max_execution time
    // is unlimited then lock for 1 hour.
    $lock_timeout = $max_execution_time > 0 ? ($max_execution_time / 1000) + 5 : static::GENERATE_LOCK_TIMEOUT;
    if (!$this->lock->acquire(static::LOCK_ID, $lock_timeout)) {
      throw new \RuntimeException('Unable to acquire a lock for sitemap generation');
    }

    foreach ($this->queue->yieldItem() as $element) {

      if (!empty($max_execution_time) && Timer::read('simple_sitemap_generator') >= $max_execution_time) {
        break;
      }

      try {
        if ($this->sitemapProcessedNow && !$this->sitemapProcessedNow->isEnabled()) {
          $this->queue->deleteItem($element);
          $this->elementsRemaining--;
          continue;
        }

        if ($this->sitemapProcessedNow === NULL || $element->data['sitemap'] !== $this->sitemapProcessedNow->id()) {

          if (NULL !== $this->sitemapProcessedNow) {
            $this->generateSitemapChunksFromResults(TRUE);
            $this->publishCurrentSitemap();
          }

          $this->processedPaths = [];
          if (($this->sitemapProcessedNow = $this->entityTypeManager->getStorage('simple_sitemap')->load($element->data['sitemap'])) === NULL) {
            $this->queue->deleteItem($element);
            $this->elementsRemaining--;
            continue;
          }
        }

        $this->generateResultsFromElement($element);

        if (!empty($this->maxLinks) && count($this->results) >= $this->maxLinks) {
          $this->generateSitemapChunksFromResults();
        }
      }
      catch (\Exception $e) {
        $this->logger->logException($e);
      }

      // @todo May want to use deleteItems() instead.
      $this->queue->deleteItem($element);
      $this->elementsRemaining--;
    }

    if ($this->getQueuedElementCount() === 0) {
      $this->generateSitemapChunksFromResults(TRUE);
      $this->publishCurrentSitemap();
    }
    else {
      $this->stashResults();
    }
    $this->lock->release(static::LOCK_ID);

    return $this;
  }

  /**
   * Generates results from the given element.
   *
   * @param mixed $element
   *   Element to process.
   */
  protected function generateResultsFromElement($element): void {
    $results = $this->sitemapProcessedNow->getType()->getUrlGenerators()[$element->data['url_generator']]
      ->setSitemap($this->sitemapProcessedNow)
      ->generate($element->data['data']);

    $this->removeDuplicates($results);
    $this->results = array_merge($this->results, $results);
  }

  /**
   * Removes duplicates from results.
   *
   * @param array $results
   *   Results to process.
   */
  protected function removeDuplicates(array &$results): void {
    if ($this->generatorSettings['remove_duplicates'] && !empty($results)) {
      foreach ($results as $key => $result) {
        if (isset($result['url'])) {
          $url = (string) $result['url'];

          if (isset($this->processedPaths[$url])) {
            unset($results[$key]);
          }
          else {
            $this->processedPaths[$url] = TRUE;
          }
        }
      }
    }
  }

  /**
   * Generates sitemap chunks from results.
   *
   * @param bool $complete
   *   The complete flag.
   */
  protected function generateSitemapChunksFromResults(bool $complete = FALSE): void {
    if (!empty($this->results)) {
      $processed_results = $this->results;
      $this->moduleHandler->alter('simple_sitemap_links', $processed_results, $this->sitemapProcessedNow);
      $this->processedResults = array_merge($this->processedResults, $processed_results);
      $this->results = [];
    }

    if (empty($this->processedResults)) {
      return;
    }

    if (!empty($this->maxLinks)) {
      foreach (array_chunk($this->processedResults, $this->maxLinks, TRUE) as $chunk_links) {
        if ($complete || count($chunk_links) === $this->maxLinks) {
          $this->sitemapProcessedNow->addChunk($chunk_links);
          $this->processedResults = array_diff_key($this->processedResults, $chunk_links);
        }
      }
    }
    else {
      $this->sitemapProcessedNow->addChunk($this->processedResults);
      $this->processedResults = [];
    }
  }

  /**
   * Publishes the current sitemap.
   */
  protected function publishCurrentSitemap(): void {
    if ($this->sitemapProcessedNow !== NULL) {
      $this->sitemapProcessedNow->generateIndex()->publish();
    }
  }

  /**
   * Resets the local cache.
   */
  protected function resetWorker(): void {
    $this->results = [];
    $this->processedPaths = [];
    $this->processedResults = [];
    $this->sitemapProcessedNow = NULL;
    $this->elementsTotal = NULL;
    $this->elementsRemaining = NULL;
  }

  /**
   * Deletes a queue and every item in the queue.
   *
   * @return $this
   */
  public function deleteQueue(): QueueWorker {
    $this->queue->deleteQueue();
    SimpleSitemap::purgeContent(NULL, SimpleSitemap::FETCH_BY_STATUS_UNPUBLISHED);
    $this->store->set('queue_items_initial_amount', 0);
    $this->store->delete('queue_stashed_results');
    $this->resetWorker();

    return $this;
  }

  /**
   * Stashes the current results.
   */
  protected function stashResults(): void {
    $this->store->set('queue_stashed_results', [
      'variant' => $this->sitemapProcessedNow ? $this->sitemapProcessedNow->id() : NULL,
      'results' => $this->results,
      'processed_results' => $this->processedResults,
      'processed_paths' => $this->processedPaths,
    ]);
    $this->resetWorker();
  }

  /**
   * Unstashes results.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function unstashResults(): void {
    if (NULL !== $results = $this->store->get('queue_stashed_results')) {
      $this->store->delete('queue_stashed_results');
      $this->results = !empty($results['results']) ? $results['results'] : [];
      $this->processedResults = !empty($results['processed_results']) ? $results['processed_results'] : [];
      $this->processedPaths = !empty($results['processed_paths']) ? $results['processed_paths'] : [];
      $this->sitemapProcessedNow = $results['variant']
        ? $this->entityTypeManager->getStorage('simple_sitemap')->load($results['variant'])
        : NULL;
    }
  }

  /**
   * Gets the initial number of queue items.
   *
   * @return int
   *   The initial number of queue items.
   */
  public function getInitialElementCount(): ?int {
    if (NULL === $this->elementsTotal) {
      $this->elementsTotal = (int) $this->store->get('queue_items_initial_amount', 0);
    }

    return $this->elementsTotal;
  }

  /**
   * Retrieves the number of items in the queue.
   *
   * @param bool $force_recount
   *   TRUE to force the recount.
   *
   * @return int
   *   An integer estimate of the number of items in the queue.
   */
  public function getQueuedElementCount(bool $force_recount = FALSE): ?int {
    if ($force_recount || NULL === $this->elementsRemaining) {
      $this->elementsRemaining = $this->queue->numberOfItems();
    }

    return $this->elementsRemaining;
  }

  /**
   * Gets the number of stashed results.
   *
   * @return int
   *   The number of stashed results.
   */
  public function getStashedResultCount(): int {
    $results = $this->store->get('queue_stashed_results', []);
    return (!empty($results['results']) ? count($results['results']) : 0)
      + (!empty($results['processed_results']) ? count($results['processed_results']) : 0);
  }

  /**
   * Gets the number of processed elements.
   *
   * @return int
   *   the number of processed elements.
   */
  public function getProcessedElementCount(): ?int {
    $initial = $this->getInitialElementCount();
    $remaining = $this->getQueuedElementCount();

    return $initial > $remaining ? ($initial - $remaining) : 0;
  }

  /**
   * Determines whether the generation is in progress.
   *
   * @return bool
   *   TRUE if generation is in progress and FALSE otherwise.
   */
  public function generationInProgress(): bool {
    return 0 < ($this->getQueuedElementCount() + $this->getStashedResultCount());
  }

}
