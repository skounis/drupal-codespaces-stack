<?php

namespace Drupal\search_api_autocomplete\Utility;

use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Defines an interface for the autocomplete search "plugin helper" service.
 */
interface PluginHelperInterface {

  /**
   * Creates a suggester plugin object for the given search.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The search for which to create the plugin.
   * @param string $plugin_id
   *   The plugin's ID.
   * @param array $configuration
   *   (optional) The configuration to set for the plugin.
   *
   * @return \Drupal\search_api_autocomplete\Suggester\SuggesterInterface
   *   The new suggester plugin object.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if an unknown plugin ID is given.
   */
  public function createSuggesterPlugin(SearchInterface $search, $plugin_id, array $configuration = []);

  /**
   * Creates a search plugin object for the given search.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The search for which to create the plugin.
   * @param string $plugin_id
   *   The plugin's ID.
   * @param array $configuration
   *   (optional) The configuration to set for the plugin.
   *
   * @return \Drupal\search_api_autocomplete\Search\SearchPluginInterface
   *   The new search plugin object.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if an unknown plugin ID is given.
   */
  public function createSearchPlugin(SearchInterface $search, $plugin_id, array $configuration = []);

  /**
   * Creates multiple suggester plugin objects for the given search.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The search for which to create the plugins.
   * @param string[]|null $plugin_ids
   *   (optional) The IDs of the plugins to create, or NULL to create instances
   *   for all known suggesters that support the given search.
   * @param array $configurations
   *   (optional) The configurations to set for the plugins, keyed by plugin ID.
   *   Missing configurations are either taken from the search's stored settings,
   *   if they are present there, or default to an empty array.
   *
   * @return \Drupal\search_api_autocomplete\Suggester\SuggesterInterface[]
   *   The created suggester plugin objects, keyed by plugin ID. If a plugin
   *   could not be created, it will be missing in the return array.
   */
  public function createSuggesterPlugins(SearchInterface $search, ?array $plugin_ids = NULL, array $configurations = []);

  /**
   * Creates objects for all search plugins associated with the given index.
   *
   * Search plugins are first filtered by their "index" definition key and then
   * via their getIndexId() method.
   *
   * @param string $index_id
   *   The ID of the search index for which to create search plugins.
   *
   * @return \Drupal\search_api_autocomplete\Search\SearchPluginInterface[]
   *   The created search plugin objects.
   */
  public function createSearchPluginsForIndex($index_id);

}
