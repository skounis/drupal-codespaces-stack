<?php

namespace Drupal\search_api_autocomplete\Tests;

use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;

/**
 * Contains helper methods for running tests.
 *
 * Needed for test callbacks since test classes in \Drupal\Tests\* cannot be
 * accessed during page requests in Functional tests.
 */
class TestsHelper {

  /**
   * Returns all features that the test backend should support.
   *
   * @return string[]
   *   The identifiers of all features this backend supports.
   *
   * @see \Drupal\search_api_test\Plugin\search_api\backend\TestBackend::getSupportedFeatures()
   */
  public static function getSupportedFeatures() {
    return ['search_api_autocomplete'];
  }

  /**
   * Retrieves autocompletion suggestions for some user input.
   *
   * @param \Drupal\search_api\Backend\BackendInterface $backend
   *   The backend on which this method was originally called.
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
   *
   * @see \Drupal\search_api_autocomplete\AutocompleteBackendInterface::getAutocompleteSuggestions()
   */
  public static function getAutocompleteSuggestions(BackendInterface $backend, QueryInterface $query, SearchInterface $search, $incomplete_key, $user_input) {
    $args = array_slice(func_get_args(), 1);
    static::logMethodCall('backend', __FUNCTION__, $args);

    $suggestions = [];
    $factory = new SuggestionFactory($user_input);
    for ($i = 1; $i <= $query->getOption('limit', 10); ++$i) {
      $suggestions[] = $factory->createFromSuggestionSuffix("-backend-$i", $i);
    }
    return $suggestions;
  }

  /**
   * Executes a search on this server.
   *
   * @param \Drupal\search_api\Backend\BackendInterface $backend
   *   The test backend on which the query is executed.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to execute.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If the index doesn't have the "entity:entity_test_mulrev_changed"
   *   datasource.
   *
   * @see \Drupal\search_api_test\Plugin\search_api\backend\TestBackend::search()
   */
  public static function search(BackendInterface $backend, QueryInterface $query) {
    $args = array_slice(func_get_args(), 1);
    static::logMethodCall('backend', __FUNCTION__, $args);

    $results = $query->getResults();
    $index = $query->getIndex();
    $datasource_id = 'entity:entity_test_mulrev_changed';
    $datasource = $index->getDatasource($datasource_id);
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');

    $result_items = [];
    foreach ([3, 4, 2] as $i) {
      $item_id = Utility::createCombinedId($datasource_id, "$i:en");
      $item = $fields_helper->createItem($index, $item_id, $datasource);
      $result_items[$item_id] = $item;
    }

    $results->setResultItems($result_items);
    $results->setResultCount(count($result_items));
  }

  /**
   * Logs a method call to the site state.
   *
   * @param string $type
   *   The type of plugin.
   * @param string $method
   *   The name of the called method.
   * @param array $args
   *   (optional) The method arguments.
   */
  protected static function logMethodCall($type, $method, array $args = []) {
    $state = \Drupal::state();

    // Log call.
    $key = "search_api_test.$type.methods_called";
    $methods_called = $state->get($key, []);
    $methods_called[] = $method;
    $state->set($key, $methods_called);

    // Log arguments.
    $key = "search_api_test.$type.method_arguments.$method";
    $state->set($key, $args);
  }

}
