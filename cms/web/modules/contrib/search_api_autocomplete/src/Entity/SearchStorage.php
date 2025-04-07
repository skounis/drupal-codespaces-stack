<?php

namespace Drupal\search_api_autocomplete\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Provides a storage handler for our search entity.
 */
class SearchStorage extends ConfigEntityStorage {

  /**
   * Loads the search that uses the given search plugin, if one exists.
   *
   * @param string $plugin_id
   *   The search plugin ID.
   *
   * @return \Drupal\search_api_autocomplete\SearchInterface|null
   *   The autocomplete search entity with that plugin, or NULL if none exists.
   */
  public function loadBySearchPlugin($plugin_id) {
    $matching_entities = $this->getQuery()
      ->exists("search_settings.$plugin_id")
      ->execute();
    return $matching_entities ? $this->load(reset($matching_entities)) : NULL;
  }

}
