<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\simple_sitemap\Annotation\SitemapGenerator;

/**
 * Manages discovery of SitemapGenerator plugins.
 */
class SitemapGeneratorManager extends DefaultPluginManager {

  /**
   * SitemapGeneratorManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/simple_sitemap/SitemapGenerator',
      $namespaces,
      $module_handler,
      SitemapGeneratorInterface::class,
      SitemapGenerator::class
    );

    $this->alterInfo('simple_sitemap_sitemap_generators');
    $this->setCacheBackend($cache_backend, 'simple_sitemap:sitemap_generator');
  }

}
