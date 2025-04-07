<?php

namespace Drupal\ai_agents\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;

/**
 * Provides an AI Agent plugin manager.
 *
 * @see \Drupal\ai_agents\Attribute\AiAgent
 * @see \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
 * @see plugin_api
 */
class AiAgentManager extends DefaultPluginManager {

  /**
   * Constructs an AI Agents object.
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
      'Plugin/AiAgent',
      $namespaces,
      $module_handler,
      AiAgentInterface::class,
      AiAgent::class,
    );
    $this->alterInfo('ai_agents_info');
    $this->setCacheBackend($cache_backend, 'ai_agents_plugins');
  }

}
