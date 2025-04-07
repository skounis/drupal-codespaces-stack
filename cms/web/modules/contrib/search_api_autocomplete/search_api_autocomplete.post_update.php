<?php

/**
 * @file
 * Post update functions for Search API Autocomplete.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Sort Search API Autocomplete entity dependencies.
 */
function search_api_autocomplete_post_update_sort_dependencies_order(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'search_api_autocomplete_search');
}
