<?php

namespace Drupal\search_api_autocomplete\Plugin;

use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Provides a common base class for autocomplete search plugins.
 */
abstract class PluginBase extends ConfigurablePluginBase implements PluginInterface {

  /**
   * The search this suggester is attached to.
   *
   * @var \Drupal\search_api_autocomplete\SearchInterface
   */
  protected $search;

  /**
   * Constructs a SearchPluginBase object.
   *
   * @param array $configuration
   *   An associative array containing the plugin's configuration, if any. The
   *   "#search" key should contain the plugin's autocomplete search entity.
   * @param string $plugin_id
   *   The plugin's ID.
   * @param array $plugin_definition
   *   The plugin's definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (!empty($configuration['#search']) && $configuration['#search'] instanceof SearchInterface) {
      $this->setSearch($configuration['#search']);
      unset($configuration['#search']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getSearch() {
    return $this->search;
  }

  /**
   * {@inheritdoc}
   */
  public function setSearch(SearchInterface $search) {
    $this->search = $search;
    return $this;
  }

}
