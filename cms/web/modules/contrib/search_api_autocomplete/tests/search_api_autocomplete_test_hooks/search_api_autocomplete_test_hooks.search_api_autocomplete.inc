<?php

/**
 * @file
 * Implements all Search API Autocomplete hooks, for testing purposes.
 */

use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggestion\Suggestion;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_search_api_autocomplete_suggestions_alter().
 */
function search_api_autocomplete_test_hooks_search_api_autocomplete_suggestions_alter(array &$suggestions, array $alter_params) {
  $suggestions[] = new Suggestion('dark chest of wonders');
}

/**
 * Implements hook_search_api_autocomplete_suggester_info_alter().
 */
function search_api_autocomplete_test_hooks_search_api_autocomplete_suggester_info_alter(array &$suggesters) {
  $suggesters['search_api_autocomplete_test']['label'] = 'Wish I Had an Angel';
}

/**
 * Implements hook_search_api_autocomplete_search_info_alter().
 */
function search_api_autocomplete_test_hooks_search_api_autocomplete_search_info_alter(array &$searches) {
  foreach (array_keys($searches) as $search_id) {
    $searches[$search_id]['group_label'] = 'The Siren';
    $searches[$search_id]['group_description'] = 'Planet Hell';
  }
}

/**
 * Implements hook_search_api_autocomplete_views_fulltext_fields_alter().
 */
function search_api_autocomplete_test_hooks_search_api_autocomplete_views_fulltext_fields_alter(array &$fields, SearchInterface $search, ViewExecutable $view) {
  \Drupal::messenger()->addStatus("Creek Mary's Blood");
}
