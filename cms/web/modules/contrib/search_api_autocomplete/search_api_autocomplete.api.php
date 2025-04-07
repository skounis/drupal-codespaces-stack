<?php

/**
 * @file
 * Hooks provided by the Search API autocomplete module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the suggestions that will be returned for a certain request.
 *
 * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[] $suggestions
 *   The suggestions that will be returned.
 * @param array $alter_params
 *   An associative array with the following keys:
 *   - query: The query generated for the request.
 *   - search: The autocomplete search entity for which suggestions are
 *     requested.
 *   - incomplete_key: The part of the user input considered to be an incomplete
 *     word. Might be empty.
 *   - user_input: The complete user input for the fulltext search keywords.
 */
function hook_search_api_autocomplete_suggestions_alter(array &$suggestions, array $alter_params) {
  // Users should really try searching for "mandelbrot" once, so just always
  // suggest that, too. In case the suggestions generated have reached the
  // limit, replace the last suggestion to this end.
  /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
  $search = $alter_params['search'];
  if (count($suggestions) >= $search->getOption('limit')) {
    array_pop($suggestions);
  }
  $suggestions[] = new \Drupal\search_api_autocomplete\Suggestion\Suggestion('mandelbrot');
}

/**
 * Alter the available suggester plugins.
 *
 * Modules may implement this hook to alter the information that defines
 * suggesters. All properties that are available in
 * \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSuggester can
 * be altered here, with the addition of the "class" and "provider" keys, and
 * any custom keys used by specific plugins.
 *
 * @param array[] $suggesters
 *   The definitions of all known suggester plugins, keyed by plugin ID.
 *
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase
 */
function hook_search_api_autocomplete_suggester_info_alter(array &$suggesters) {
  if (!empty($suggesters['example_suggester'])) {
    $suggesters['example_suggester']['class'] = '\Drupal\my_module\MuchBetterSuggester';
  }
}

/**
 * Alter the available search plugins.
 *
 * Modules may implement this hook to alter the information that defines
 * searches. All properties that are available in
 * \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSearch can
 * be altered here, with the addition of the "class" and "provider" keys, and
 * any custom keys used by specific plugins.
 *
 * @param array[] $searches
 *   The definitions of all known search plugins, keyed by plugin ID.
 *
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginBase
 */
function hook_search_api_autocomplete_search_info_alter(array &$searches) {
  if (!empty($searches['example_search'])) {
    $searches['example_search']['class'] = '\Drupal\my_module\MuchBetterSearchPlugin';
  }
}

/**
 * Alter the Views fields that are considered "fulltext" fields.
 *
 * If autocomplete is enabled for a certain search view, autocompletion will be
 * added to all text fields that belong to any of those fulltext fields.
 *
 * @param string[] $fields
 *   The fields considered to be fulltext fields. These are the "real field"
 *   values in their Views data definition. By default, all Views fields added
 *   for an index's fulltext fields are included, plus "search_api_fulltext".
 * @param \Drupal\search_api_autocomplete\SearchInterface $search
 *   The search for which to get the fulltext fields.
 * @param \Drupal\views\ViewExecutable $view
 *   The view for which to get the fulltext fields.
 */
function hook_search_api_autocomplete_views_fulltext_fields_alter(array &$fields, \Drupal\search_api_autocomplete\SearchInterface $search, \Drupal\views\ViewExecutable $view) {
  if ($view->id() === 'my_view' || $search->getIndexId() === 'my_index') {
    $fields[] = 'my_field';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
