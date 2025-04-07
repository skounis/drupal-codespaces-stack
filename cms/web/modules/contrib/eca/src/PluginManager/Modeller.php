<?php

namespace Drupal\eca\PluginManager;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\eca\Annotation\EcaModeller;
use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;

/**
 * ECA modeller plugin manager.
 */
class Modeller extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs PluginManager object.
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
      'Plugin/ECA/Modeller',
      $namespaces,
      $module_handler,
      ModellerInterface::class,
      EcaModeller::class
    );
    $this->alterInfo('eca_modeller_info');
    $this->setCacheBackend($cache_backend, 'eca_modeller_plugins', ['eca_modeller_plugins']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []): string {
    return 'fallback';
  }

}
