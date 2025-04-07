<?php

namespace Drupal\project_browser\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\project_browser\Attribute\ProjectBrowserSource;

/**
 * Provides a Project Browser Source Manager.
 *
 * @see \Drupal\project_browser\Attribute\ProjectBrowserSource
 * @see \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
 * @see plugin_api
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
final class ProjectBrowserSourceManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ProjectBrowserSource',
      $namespaces,
      $module_handler,
      ProjectBrowserSourceInterface::class,
      ProjectBrowserSource::class,
    );

    $this->alterInfo('project_browser_source_info');
    $this->setCacheBackend($cache_backend, 'project_browser_source_info_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
   *   The source plugin.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $instance = parent::createInstance($plugin_id, $configuration);
    assert($instance instanceof ProjectBrowserSourceInterface);
    return $instance;
  }

}
