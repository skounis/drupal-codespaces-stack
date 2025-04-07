<?php

namespace Drupal\simple_sitemap_engines\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\simple_sitemap_engines\Submitter\SitemapSubmitter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of search engines to submit sitemaps.
 *
 * @QueueWorker(
 *   id = "simple_sitemap_engine_submit",
 *   title = @Translation("Sitemap search engine submission"),
 *   cron = {"time" = 30}
 * )
 *
 * @see simple_sitemap_engines_cron()
 */
class SitemapSubmittingWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The sitemap_submitter service.
   *
   * @var \Drupal\simple_sitemap_engines\Submitter\SitemapSubmitter
   */
  protected $sitemapSubmitter;

  /**
   * SitemapSubmitter constructor.
   *
   * @param array $configuration
   *   The config.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\simple_sitemap_engines\Submitter\SitemapSubmitter $sitemap_submitter
   *   Sitemap submitter service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    SitemapSubmitter $sitemap_submitter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sitemapSubmitter = $sitemap_submitter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SitemapSubmittingWorker {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.engines.sitemap_submitter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($engine_id) {
    $this->sitemapSubmitter->process($engine_id);
  }

}
