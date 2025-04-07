<?php

namespace Drupal\search_api_autocomplete;

@trigger_error('\Drupal\search_api_autocomplete\AutocompleteBackendInterface is deprecated in search_api_autocomplete:8.x-1.6 and is removed from search_api_autocomplete:2.0.0. Use \Drupal\search_api\Contrib\AutocompleteBackendInterface instead. See https://www.drupal.org/node/3256202.', E_USER_DEPRECATED);

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendInterface;

/**
 * Describes the method a backend plugin has to add to support autocompletion.
 *
 * In addition, the backend has to include "search_api_autocomplete" in the
 * return value of its getSupportedFeatures() implementation.
 *
 * Note that this interface is purely for the purpose of documentation. You
 * should not, and cannot, implement it explicitly (unless your module is
 * depending on this one).
 *
 * @deprecated in search_api_autocomplete:8.x-1.6 and is removed from
 *   search_api_autocomplete:2.0.0. Use
 *   \Drupal\search_api\Contrib\AutocompleteBackendInterface instead.
 *
 * @see https://www.drupal.org/node/3256202
 */
interface AutocompleteBackendInterface extends BackendInterface {

  /**
   * Retrieves autocompletion suggestions for some user input.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   An object containing details about the search the user is on, and
   *   settings for the autocompletion. See the class documentation for details.
   *   Especially $search->getOptions() should be checked for settings, like
   *   whether to try and estimate result counts for returned suggestions.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   */
  public function getAutocompleteSuggestions(QueryInterface $query, SearchInterface $search, $incomplete_key, $user_input);

}
