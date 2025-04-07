<?php

namespace Drupal\search_api_autocomplete\Search;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSearch;

/**
 * Provides a plugin manager for autocomplete search plugins.
 *
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSearch
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginInterface
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginBase
 * @see plugin_api
 */
class SearchPluginManager extends DefaultPluginManager {

  /**
   * Constructs a SearchPluginManager object.
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
    parent::__construct('Plugin/search_api_autocomplete/search', $namespaces, $module_handler, SearchPluginInterface::class, SearchApiAutocompleteSearch::class);

    $this->setCacheBackend($cache_backend, 'search_api_autocomplete_search_plugin');
    $this->alterInfo('search_api_autocomplete_search_info');
  }

}
