<?php

namespace Drupal\search_api_autocomplete\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_autocomplete\SearchApiAutocompleteException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterInterface;

/**
 * Describes the autocomplete settings for a certain search.
 *
 * @ConfigEntityType(
 *   id = "search_api_autocomplete_search",
 *   label = @Translation("Autocomplete search"),
 *   label_collection = @Translation("Autocomplete searches"),
 *   label_singular = @Translation("autocomplete search"),
 *   label_plural = @Translation("autocomplete searches"),
 *   label_count = @PluralTranslation(
 *     singular = "@count autocomplete search",
 *     plural = "@count autocomplete searches",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\search_api_autocomplete\Entity\SearchStorage",
 *     "form" = {
 *       "default" = "\Drupal\search_api_autocomplete\Form\SearchEditForm",
 *       "edit" = "\Drupal\search_api_autocomplete\Form\SearchEditForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "\Drupal\Core\Entity\EntityListBuilder",
 *     "route_provider" = {
 *       "default" = "\Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer search_api_autocomplete",
 *   config_prefix = "search",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/search/search-api/index/{search_api_index}/autocomplete/{search_api_autocomplete_search}/edit",
 *     "delete-form" = "/admin/config/search/search-api/index/{search_api_index}/autocomplete/{search_api_autocomplete_search}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "index_id",
 *     "suggester_settings",
 *     "suggester_weights",
 *     "suggester_limits",
 *     "search_settings",
 *     "options",
 *   }
 * )
 */
class Search extends ConfigEntityBase implements SearchInterface {

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity label.
   *
   * @var string
   */
  protected $label;

  /**
   * The index ID.
   *
   * @var string
   */
  protected $index_id;

  /**
   * The search index instance.
   *
   * @var \Drupal\search_api\IndexInterface|null
   *
   * @see \Drupal\search_api_autocomplete\Entity\Search::getIndex()
   */
  protected $index;

  /**
   * The settings of the suggesters selected for this search.
   *
   * The array has the following structure:
   *
   * @code
   * [
   *   'SUGGESTER_ID' => [
   *     // Settings …
   *   ],
   *   …
   * ]
   * @endcode
   *
   * @var array
   */
  protected $suggester_settings = [];

  /**
   * The suggester weights, keyed by suggester ID.
   *
   * @var int[]
   */
  protected $suggester_weights = [];

  /**
   * The suggester limits (where set), keyed by suggester ID.
   *
   * @var int[]
   */
  protected $suggester_limits = [];

  /**
   * The loaded suggester plugins.
   *
   * @var \Drupal\search_api_autocomplete\Suggester\SuggesterInterface[]|null
   */
  protected $suggesterInstances;

  /**
   * The settings for the search plugin.
   *
   * The array has the following structure:
   *
   * @code
   * [
   *   'SEARCH_ID' => [
   *     // Settings …
   *   ]
   * ]
   * @endcode
   *
   * There is always just a single entry in the array.
   *
   * @var array
   */
  protected $search_settings = [];

  /**
   * The search plugin.
   *
   * @var \Drupal\search_api_autocomplete\Search\SearchPluginInterface|null
   */
  protected $searchPlugin;

  /**
   * An array of general options for this search.
   *
   * @var array
   */
  protected $options = [];

  /**
   * {@inheritdoc}
   */
  public static function getDefaultOptions() {
    return [
      'autosubmit' => TRUE,
      'delay' => NULL,
      'limit' => 10,
      'min_length' => 1,
      'submit_button_selector' => ':submit',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $parameters = parent::urlRouteParameters($rel);

    $parameters['search_api_index'] = $this->getIndexId();

    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId() {
    return $this->index_id;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidIndex() {
    return $this->index || Index::load($this->index_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    if (!isset($this->index)) {
      $this->index = Index::load($this->index_id);
      if (!$this->index) {
        throw new SearchApiAutocompleteException("The index with ID \"{$this->index_id}\" could not be loaded.");
      }
    }
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesters() {
    if ($this->suggesterInstances === NULL) {
      $this->suggesterInstances = \Drupal::getContainer()
        ->get('search_api_autocomplete.plugin_helper')
        ->createSuggesterPlugins($this, array_keys($this->suggester_settings));
    }

    return $this->suggesterInstances;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterIds() {
    if ($this->suggesterInstances !== NULL) {
      return array_keys($this->suggesterInstances);
    }
    return array_keys($this->suggester_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function isValidSuggester($suggester_id) {
    $suggesters = $this->getSuggesters();
    return !empty($suggesters[$suggester_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggester($suggester_id) {
    $suggesters = $this->getSuggesters();

    if (empty($suggesters[$suggester_id])) {
      $index_label = $this->label();
      throw new SearchApiAutocompleteException("The suggester with ID '$suggester_id' could not be retrieved for index '$index_label'.");
    }

    return $suggesters[$suggester_id];
  }

  /**
   * {@inheritdoc}
   */
  public function addSuggester(SuggesterInterface $suggester) {
    // Make sure the suggesterInstances are loaded before trying to add a plugin
    // to them.
    if ($this->suggesterInstances === NULL) {
      $this->getSuggesters();
    }
    $this->suggesterInstances[$suggester->getPluginId()] = $suggester;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSuggester($suggester_id) {
    // Depending on whether the suggesters have already been loaded, we have to
    // either remove the settings or the instance.
    if ($this->suggesterInstances === NULL) {
      unset($this->suggester_settings[$suggester_id]);
    }
    else {
      unset($this->suggesterInstances[$suggester_id]);
    }
    unset($this->suggester_weights[$suggester_id]);
    unset($this->suggester_limits[$suggester_id]);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggesters(?array $suggesters = NULL) {
    $this->suggesterInstances = $suggesters;

    // Sanitize the suggester weights and limits.
    $this->suggester_weights = array_intersect_key($this->suggester_weights, $suggesters);
    $this->suggester_limits = array_intersect_key($this->suggester_limits, $suggesters);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterWeights() {
    return $this->suggester_weights;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterLimits() {
    return $this->suggester_limits;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidSearchPlugin() {
    return (bool) \Drupal::getContainer()
      ->get('plugin.manager.search_api_autocomplete.search')
      ->getDefinition($this->getSearchPluginId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchPluginId() {
    if ($this->searchPlugin) {
      return $this->searchPlugin->getPluginId();
    }
    reset($this->search_settings);
    return key($this->search_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchPlugin() {
    if (!$this->searchPlugin) {
      $plugin_id = $this->getSearchPluginId();

      $configuration = [];
      if (!empty($this->search_settings[$plugin_id])) {
        $configuration = $this->search_settings[$plugin_id];
      }

      $this->searchPlugin = \Drupal::getContainer()
        ->get('search_api_autocomplete.plugin_helper')
        ->createSearchPlugin($this, $plugin_id, $configuration);
    }

    return $this->searchPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name) {
    $options = $this->getOptions();
    return isset($options[$name]) ? $options[$name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options + static::getDefaultOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $option) {
    $this->options[$name] = $option;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function createQuery($keys, array $data = []) {
    return $this->getSearchPlugin()->createQuery($keys, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Make sure the search plugin's index matches this entity's index.
    $plugin_index_id = $this->getSearchPlugin()->getIndexId();
    if ($this->getIndexId() !== $plugin_index_id) {
      throw new SearchApiAutocompleteException("Attempt to save autocomplete search '{$this->id()}' with search plugin '{$this->getSearchPluginId()}' of index '$plugin_index_id' while the autocomplete search points to index '{$this->getIndexId()}'");
    }

    // Make sure only one search entity is ever saved for a certain search
    // plugin.
    /** @var \Drupal\search_api_autocomplete\Entity\SearchStorage $storage */
    $search = $storage->loadBySearchPlugin($this->getSearchPluginId());
    if ($search && $search->id() !== $this->id()) {
      throw new SearchApiAutocompleteException("Attempt to save autocomplete search '{$this->id()}' with search plugin '{$this->getSearchPluginId()}' when this plugin is already used for '{$search->id()}'");
    }

    // If we are in the process of syncing, we shouldn't change any entity
    // properties (or other configuration).
    if ($this->isSyncing()) {
      return;
    }

    // Write the plugin settings to the persistent *_settings properties.
    $this->writeChangesToSettings();

    // If there are no suggesters set for the search, it can't be enabled.
    if (!$this->getSuggesters()) {
      $vars = ['@name' => $this->label() ?: $this->id()];
      \Drupal::logger('search_api_autocomplete')->warning('Autocomplete search @name was disabled because it has no enabled suggesters.', $vars);
      \Drupal::messenger()->addWarning(t('Autocomplete search @name was disabled because it has no enabled suggesters.', $vars));
      $this->disable();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    parent::invalidateTagsOnSave($update);

    $plugin_id = $this->getSearchPluginId();
    Cache::invalidateTags(["search_api_autocomplete_search_list:$plugin_id"]);
  }

  /**
   * {@inheritdoc}
   */
  protected static function invalidateTagsOnDelete(EntityTypeInterface $entity_type, array $entities) {
    parent::invalidateTagsOnDelete($entity_type, $entities);

    $tags = [];
    foreach ($entities as $entity) {
      if ($entity instanceof Search) {
        $plugin_id = $entity->getSearchPluginId();
        $tags[] = "search_api_autocomplete_search_list:$plugin_id";
      }
    }
    if ($tags) {
      Cache::invalidateTags(array_unique($tags));
    }
  }

  /**
   * Prepares for changes to this search to be persisted.
   *
   * To this end, the settings for all loaded plugin objects are written back to
   * the corresponding *_settings properties.
   *
   * @return $this
   */
  protected function writeChangesToSettings() {
    // Write the enabled suggesters to the settings property.
    $this->suggester_settings = [];
    foreach ($this->getSuggesters() as $suggester_id => $suggester) {
      if ($suggester->supportsSearch($this)) {
        $configuration = $suggester->getConfiguration();
        $this->suggester_settings[$suggester_id] = $configuration;
      }
      else {
        $vars = [
          '@suggester' => $suggester->label() ?: $suggester->getPluginId(),
          '@search' => $this->label() ?: $this->id(),
        ];
        \Drupal::logger('search_api_autocomplete')->warning('Disabled unsupported suggester @suggester for autocomplete search @search.', $vars);
        \Drupal::messenger()->addWarning(t('Disabled unsupported suggester @suggester for autocomplete search @search.', $vars));
        unset($this->suggesterInstances[$suggester_id]);
      }
    }

    // Write the search plugin configuration to the settings property.
    if ($this->searchPlugin !== NULL) {
      $plugin_id = $this->searchPlugin->getPluginId();
      $this->search_settings = [
        $plugin_id => $this->searchPlugin->getConfiguration(),
      ];
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = $this->getDependencyData();
    // Keep only "enforced" dependencies, then add those computed by
    // getDependencyData().
    $this->dependencies = array_intersect_key($this->dependencies, ['enforced' => TRUE]);
    $this->addDependencies(array_map('array_keys', $dependencies));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    $plugins = $this->getAllPlugins();
    $dependency_data = $this->getDependencyData();
    // Make sure our dependency data has the exact same keys as $dependencies,
    // to simplify the subsequent code.
    $dependencies = array_filter($dependencies);
    $dependency_data = array_intersect_key($dependency_data, $dependencies);
    $dependency_data += array_fill_keys(array_keys($dependencies), []);
    $call_on_removal = [];

    foreach ($dependencies as $dependency_type => $dependency_objects) {
      // Annoyingly, modules and theme dependencies come not keyed by dependency
      // name here, while entities do. Flip the array for modules and themes to
      // make the code simpler.
      if (in_array($dependency_type, ['module', 'theme'])) {
        $dependency_objects = array_flip($dependency_objects);
      }
      $dependency_data[$dependency_type] = array_intersect_key($dependency_data[$dependency_type], $dependency_objects);
      foreach ($dependency_data[$dependency_type] as $name => $dependency_sources) {
        // We first remove all the "hard" dependencies.
        if (!empty($dependency_sources['always'])) {
          foreach ($dependency_sources['always'] as $plugin_type => $type_plugins) {
            // We can hardly remove the search entity itself.
            if ($plugin_type == 'entity') {
              continue;
            }

            // Otherwise, we need to remove the plugin in question.
            $changed = TRUE;
            $plugins[$plugin_type] = array_diff_key($plugins[$plugin_type], $type_plugins);
          }
        }

        // Then, collect all the optional ones.
        if (!empty($dependency_sources['optional'])) {
          // However this plays out, it will lead to a change.
          $changed = TRUE;

          foreach ($dependency_sources['optional'] as $plugin_type => $type_plugins) {
            // Only include those plugins that have not already been removed.
            $type_plugins = array_intersect_key($type_plugins, $plugins[$plugin_type]);

            foreach ($type_plugins as $plugin_id => $plugin) {
              $call_on_removal[$plugin_type][$plugin_id][$dependency_type][$name] = $dependency_objects[$name];
            }
          }
        }
      }
    }

    // Now for all plugins with optional dependencies (stored in
    // $call_on_removal, mapped to their removed dependencies) call their
    // onDependencyRemoval() methods.
    foreach ($call_on_removal as $plugin_type => $type_plugins) {
      foreach ($type_plugins as $plugin_id => $plugin_dependencies) {
        $plugin = $plugins[$plugin_type][$plugin_id];
        $removal_successful = $plugin->onDependencyRemoval($plugin_dependencies);
        if (!$removal_successful) {
          unset($plugins[$plugin_type][$plugin_id]);
        }
      }
    }

    // If we had to remove the search plugin, the search entity cannot be
    // "rescued". Return FALSE in this case, which will cause the entity to be
    // deleted.
    if (empty($plugins['search_plugin'])) {
      return FALSE;
    }

    // If there are no suggesters left, we just disable the search.
    if (empty($plugins['suggesters'])) {
      $this->disable();
    }

    // In case we removed any suggesters, set our suggesters to the remaining
    // ones. (If we didn't remove any, this is a no-op.) Since the plugins
    // already changed their configuration, if necessary, those changes should
    // be propagated automatically when saving via preSave().
    $this->setSuggesters($plugins['suggesters']);

    return $changed;
  }

  /**
   * Retrieves data about this search entity's dependencies.
   *
   * The return value is structured as follows:
   *
   * @code
   * [
   *   'config' => [
   *     'CONFIG_DEPENDENCY_KEY' => [
   *       'always' => [
   *         'search_plugin' => [
   *           'SEARCH_ID' => $search_plugin,
   *         ],
   *         'suggesters' => [
   *           'SUGGESTER_ID_1' => $suggester_1,
   *           'SUGGESTER_ID_2' => $suggester_2,
   *         ],
   *       ],
   *       'optional' => [
   *         'entity' => [
   *           'SEARCH_ID' => $search,
   *         ],
   *       ],
   *     ],
   *   ],
   * ]
   * @endcode
   *
   * Enforced dependencies are not included in this method's return value.
   *
   * @return object[][][][][]
   *   An associative array containing the search's dependencies. The array is
   *   first keyed by the config dependency type ("module", "config", etc.) and
   *   then by the names of the config dependencies of that type which the index
   *   has. The values are associative arrays with up to two keys, "always" and
   *   "optional", specifying whether the dependency is a hard one by the plugin
   *   (or entity) in question or potentially depending on the configuration.
   *   The values on this level are arrays with keys "entity", "search_plugin"
   *   and/or "suggesters" and values arrays of IDs mapped to their entities or
   *   plugins.
   */
  protected function getDependencyData() {
    $dependency_data = [];

    // Since calculateDependencies() will work directly on the $dependencies
    // property, we first save its original state and then restore it
    // afterwards.
    $original_dependencies = $this->dependencies;
    parent::calculateDependencies();
    unset($this->dependencies['enforced']);
    foreach ($this->dependencies as $dependency_type => $list) {
      foreach ($list as $name) {
        $dependency_data[$dependency_type][$name]['always']['entity'][$this->id] = $this;
      }
    }
    $this->dependencies = $original_dependencies;

    // Include the dependency to the search index.
    if ($this->hasValidIndex()) {
      $name = $this->getIndex()->getConfigDependencyName();
      $dependency_data['config'][$name]['always']['entity'][$this->id] = $this;
    }

    // All other plugins can be treated uniformly.
    foreach ($this->getAllPlugins() as $plugin_type => $type_plugins) {
      foreach ($type_plugins as $plugin_id => $plugin) {
        // Largely copied from
        // \Drupal\Core\Plugin\PluginDependencyTrait::calculatePluginDependencies().
        $definition = $plugin->getPluginDefinition();

        // First, always depend on the module providing the plugin.
        $dependency_data['module'][$definition['provider']]['always'][$plugin_type][$plugin_id] = $plugin;

        // Plugins can declare additional dependencies in their definition.
        if (isset($definition['config_dependencies'])) {
          foreach ($definition['config_dependencies'] as $dependency_type => $list) {
            foreach ($list as $name) {
              $dependency_data[$dependency_type][$name]['always'][$plugin_type][$plugin_id] = $plugin;
            }
          }
        }

        // Finally, add the dynamically-calculated dependencies of the plugin.
        foreach ($plugin->calculateDependencies() as $dependency_type => $list) {
          foreach ($list as $name) {
            $dependency_data[$dependency_type][$name]['optional'][$plugin_type][$plugin_id] = $plugin;
          }
        }
      }
    }

    return $dependency_data;
  }

  /**
   * Retrieves all the plugins contained in this search entity.
   *
   * @return \Drupal\search_api_autocomplete\Plugin\PluginInterface[][]
   *   All plugins contained in this search, keyed by the plugin type
   *   ("search_plugin" or "suggesters") and their plugin IDs.
   */
  protected function getAllPlugins() {
    $plugins = [];

    if ($this->hasValidSearchPlugin()) {
      $plugin_id = $this->getSearchPluginId();
      $plugins['search_plugin'][$plugin_id] = $this->getSearchPlugin();
    }
    $plugins['suggesters'] = $this->getSuggesters();

    return $plugins;
  }

}
