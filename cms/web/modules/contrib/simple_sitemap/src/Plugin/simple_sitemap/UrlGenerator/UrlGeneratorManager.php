<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\simple_sitemap\Annotation\UrlGenerator;

/**
 * Manages discovery of UrlGenerator plugins.
 */
class UrlGeneratorManager extends DefaultPluginManager {

  /**
   * UrlGeneratorManager constructor.
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
      'Plugin/simple_sitemap/UrlGenerator',
      $namespaces,
      $module_handler,
      UrlGeneratorInterface::class,
      UrlGenerator::class
    );

    $this->alterInfo('simple_sitemap_url_generators');
    $this->setCacheBackend($cache_backend, 'simple_sitemap:url_generator');
  }

}
