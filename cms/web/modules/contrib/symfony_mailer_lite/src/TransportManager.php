<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the mailer transport plugin manager.
 */
class TransportManager extends DefaultPluginManager {

  /**
   * Constructs a RecipientHandlerManager object.
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
    parent::__construct('Plugin/SymfonyMailerLite/Transport', $namespaces, $module_handler, 'Drupal\symfony_mailer_lite\TransportPluginInterface', 'Drupal\symfony_mailer_lite\Annotation\SymfonyMailerLiteTransport');
    $this->setCacheBackend($cache_backend, 'symfony_mailer_lite_transport_plugins');
    $this->alterInfo('symfony_mailer_lite_transport_info');
  }

}
