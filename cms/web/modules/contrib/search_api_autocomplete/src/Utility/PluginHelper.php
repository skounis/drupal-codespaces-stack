<?php

namespace Drupal\search_api_autocomplete\Utility;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\search_api_autocomplete\SearchApiAutocompleteException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterManager;
use Drupal\search_api_autocomplete\Search\SearchPluginManager;

/**
 * Provides methods for creating autocomplete search plugins.
 */
class PluginHelper implements PluginHelperInterface {

  /**
   * The suggester plugin manager.
   *
   * @var \Drupal\search_api_autocomplete\Suggester\SuggesterManager
   */
  protected $suggesterPluginManager;

  /**
   * The search plugin manager.
   *
   * @var \Drupal\search_api_autocomplete\Search\SearchPluginManager
   */
  protected $searchPluginManager;

  /**
   * Constructs a PluginHelper object.
   *
   * @param \Drupal\search_api_autocomplete\Suggester\SuggesterManager $suggester_plugin_manager
   *   The suggester plugin manager.
   * @param \Drupal\search_api_autocomplete\Search\SearchPluginManager $search_plugin_manager
   *   The search plugin manager.
   */
  public function __construct(SuggesterManager $suggester_plugin_manager, SearchPluginManager $search_plugin_manager) {
    $this->suggesterPluginManager = $suggester_plugin_manager;
    $this->searchPluginManager = $search_plugin_manager;
  }

  /**
   * Creates a plugin object for the given search.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The search for which to create the plugins.
   * @param string $type
   *   The type of plugin to create: "suggester" or "search".
   * @param string $plugin_id
   *   The plugin's ID.
   * @param array $configuration
   *   (optional) The configuration to set for the plugin.
   *
   * @return \Drupal\search_api_autocomplete\Plugin\PluginInterface
   *   The new plugin object.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if an unknown $type or $plugin_id is given.
   */
  protected function createPlugin(SearchInterface $search, $type, $plugin_id, array $configuration = []) {
    if (!isset($this->{$type . "PluginManager"})) {
      throw new SearchApiAutocompleteException("Unknown plugin type '$type'");
    }

    try {
      $configuration['#search'] = $search;
      return $this->{$type . "PluginManager"}->createInstance($plugin_id, $configuration);
    }
    catch (PluginException $e) {
      throw new SearchApiAutocompleteException("Unknown $type plugin with ID '$plugin_id'", 0, $e);
    }
  }

  /**
   * Creates multiple plugin objects for the given search.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The search for which to create the plugins.
   * @param string $type
   *   The type of plugin to create: "suggester" or "search".
   * @param string[]|null $plugin_ids
   *   (optional) The IDs of the plugins to create, or NULL to create instances
   *   for all known plugins of this type.
   * @param array $configurations
   *   (optional) The configurations to set for the plugins, keyed by plugin ID.
   *   Missing configurations are either taken from the search's stored settings,
   *   if they are present there, or default to an empty array.
   *
   * @return \Drupal\search_api_autocomplete\Plugin\PluginInterface[]
   *   The created plugin objects.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if an unknown $type is given.
   */
  protected function createPlugins(SearchInterface $search, $type, ?array $plugin_ids = NULL, array $configurations = []) {
    if (!isset($this->{$type . "PluginManager"})) {
      throw new SearchApiAutocompleteException("Unknown plugin type '$type'");
    }

    if ($plugin_ids === NULL) {
      $plugin_ids = array_keys($this->{$type . "PluginManager"}->getDefinitions());
    }

    $plugins = [];
    $search_settings = $search->get($type . '_settings');
    foreach ($plugin_ids as $plugin_id) {
      $configuration = [];
      if (isset($configurations[$plugin_id])) {
        $configuration = $configurations[$plugin_id];
      }
      elseif (isset($search_settings[$plugin_id])) {
        $configuration = $search_settings[$plugin_id];
      }
      try {
        $plugins[$plugin_id] = $this->createPlugin($search, $type, $plugin_id, $configuration);
      }
      catch (SearchApiAutocompleteException $e) {
        // Ignore unknown plugins.
      }
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function createSuggesterPlugin(SearchInterface $search, $plugin_id, array $configuration = []) {
    return $this->createPlugin($search, 'suggester', $plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function createSearchPlugin(SearchInterface $search, $plugin_id, array $configuration = []) {
    return $this->createPlugin($search, 'search', $plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function createSuggesterPlugins(SearchInterface $search, ?array $plugin_ids = NULL, array $configurations = []) {
    return $this->createPlugins($search, 'suggester', $plugin_ids, $configurations);
  }

  /**
   * {@inheritdoc}
   */
  public function createSearchPluginsForIndex($index_id) {
    $definitions = $this->searchPluginManager->getDefinitions();
    $searches = [];
    foreach ($definitions as $search_id => $definition) {
      if (!empty($definition['index']) && $definition['index'] !== $index_id) {
        continue;
      }
      /** @var \Drupal\search_api_autocomplete\Search\SearchPluginInterface $search */
      $search = $this->searchPluginManager->createInstance($search_id);
      if ($search->getIndexId() === $index_id) {
        $searches[$search_id] = $search;
      }
    }

    return $searches;
  }

}
