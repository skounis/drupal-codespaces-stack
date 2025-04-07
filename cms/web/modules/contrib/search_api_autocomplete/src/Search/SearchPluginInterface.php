<?php

namespace Drupal\search_api_autocomplete\Search;

use Drupal\search_api_autocomplete\Plugin\PluginInterface;

/**
 * Defines the autocomplete search plugin type.
 *
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSearch
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginManager
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginBase
 * @see plugin_api
 */
interface SearchPluginInterface extends PluginInterface {

  /**
   * Retrieves a group label for this search.
   *
   * Used to group searches from the same source together in the UI.
   *
   * @return string
   *   A translated, human-readable label to group the search by.
   */
  public function getGroupLabel();

  /**
   * Retrieves a description for this search's group.
   *
   * Searches with the same group label should aim to also return the same group
   * description.
   *
   * @return string
   *   A translated, human-readable description for this search's group.
   */
  public function getGroupDescription();

  /**
   * Retrieves the ID of the index to which this search plugin belongs.
   *
   * @return string
   *   The search plugin's index's ID.
   */
  public function getIndexId();

  /**
   * Retrieves the index to which this search plugin belongs.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search plugin's index.
   */
  public function getIndex();

  /**
   * Creates a search query based on this search.
   *
   * @param string $keys
   *   The keywords to set on the query, if possible. Otherwise, this parameter
   *   can also be ignored.
   * @param array $data
   *   (optional) Additional data passed to the callback.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The created query.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if the query couldn't be created.
   */
  public function createQuery($keys, array $data = []);

}
