<?php

namespace Drupal\sitemap;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an Sitemap plugin manager.
 *
 * @see \Drupal\sitemap\Annotation\Sitemap
 * @see \Drupal\sitemap\SitemapInterface
 * @see plugin_api
 */
class SitemapManager extends DefaultPluginManager {

  /**
   * Constructs a SitemapManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Sitemap',
      $namespaces,
      $module_handler,
      'Drupal\sitemap\SitemapInterface',
      'Drupal\sitemap\Annotation\Sitemap'
    );
    $this->alterInfo('sitemap_info');
    $this->setCacheBackend($cache_backend, 'sitemap_info_plugins');
  }

}
