<?php

namespace Drupal\search_api_autocomplete;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterInterface;

/**
 * Describes the autocomplete settings for a certain search.
 */
interface SearchInterface extends ConfigEntityInterface {

  /**
   * Retrieves the default options for a search.
   *
   * @return array
   *   An associative array of options.
   */
  public static function getDefaultOptions();

  /**
   * Retrieves the ID of the index this search belongs to.
   *
   * @return string
   *   The index ID.
   */
  public function getIndexId();

  /**
   * Determines whether this search has a valid index set.
   *
   * @return bool
   *   TRUE if the index this search belongs to can be loaded, FALSE otherwise.
   */
  public function hasValidIndex();

  /**
   * Retrieves the index this search belongs to.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index this search belongs to.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if the index couldn't be retrieved.
   */
  public function getIndex();

  /**
   * Retrieves this search's suggester plugins.
   *
   * @return \Drupal\search_api_autocomplete\Suggester\SuggesterInterface[]
   *   The suggester plugins used by this search, keyed by plugin ID.
   */
  public function getSuggesters();

  /**
   * Retrieves the IDs of all suggesters enabled for this search.
   *
   * @return string[]
   *   The IDs of the suggester plugins used by this search.
   */
  public function getSuggesterIds();

  /**
   * Determines whether the given suggester ID is valid for this search.
   *
   * The general contract of this method is that it should return TRUE if, and
   * only if, a call to getSuggester() with the same ID would not result in an
   * exception.
   *
   * @param string $suggester_id
   *   A suggester plugin ID.
   *
   * @return bool
   *   TRUE if the suggester with the given ID is enabled for this search and
   *   can be loaded. FALSE otherwise.
   */
  public function isValidSuggester($suggester_id);

  /**
   * Retrieves a specific suggester plugin for this search.
   *
   * @param string $suggester_id
   *   The ID of the suggester plugin to return.
   *
   * @return \Drupal\search_api_autocomplete\Suggester\SuggesterInterface
   *   The suggester plugin with the given ID.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if the specified suggester isn't enabled for this search, or
   *   couldn't be loaded.
   */
  public function getSuggester($suggester_id);

  /**
   * Adds a suggester to this search.
   *
   * An existing suggester with the same ID will be replaced.
   *
   * @param \Drupal\search_api_autocomplete\Suggester\SuggesterInterface $suggester
   *   The suggester to be added.
   *
   * @return $this
   */
  public function addSuggester(SuggesterInterface $suggester);

  /**
   * Removes a suggester from this search.
   *
   * @param string $suggester_id
   *   The ID of the suggester to remove.
   *
   * @return $this
   */
  public function removeSuggester($suggester_id);

  /**
   * Sets this search's suggester plugins.
   *
   * @param \Drupal\search_api_autocomplete\Suggester\SuggesterInterface[] $suggesters
   *   An array of suggesters.
   *
   * @return $this
   */
  public function setSuggesters(array $suggesters);

  /**
   * Retrieves the weights set for the search's suggesters.
   *
   * @return int[]
   *   The suggester weights, keyed by suggester ID.
   */
  public function getSuggesterWeights();

  /**
   * Retrieves the individual limits set for the search's suggesters.
   *
   * @return int[]
   *   The suggester limits (where set), keyed by suggester ID.
   */
  public function getSuggesterLimits();

  /**
   * Determines whether the search plugin set for this search is valid.
   *
   * @return bool
   *   TRUE if the search plugin is valid, FALSE otherwise.
   */
  public function hasValidSearchPlugin();

  /**
   * Retrieves the search plugin's ID.
   *
   * @return string
   *   The ID of the search plugin used by this search.
   */
  public function getSearchPluginId();

  /**
   * Retrieves the search plugin.
   *
   * @return \Drupal\search_api_autocomplete\Search\SearchPluginInterface
   *   The search's search plugin.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if the search plugin couldn't be instantiated.
   */
  public function getSearchPlugin();

  /**
   * Gets a specific option's value.
   *
   * @param string $key
   *   The key of the option.
   *
   * @return mixed|null
   *   The option's value, or NULL if the option is unknown.
   */
  public function getOption($key);

  /**
   * Gets the search's options.
   *
   * @return array
   *   The options.
   */
  public function getOptions();

  /**
   * Sets an option.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $option
   *   The new option.
   *
   * @return $this
   */
  public function setOption($name, $option);

  /**
   * Sets the search options.
   *
   * @param array $options
   *   The options.
   *
   * @return $this
   */
  public function setOptions(array $options);

  /**
   * Creates a query object for this search.
   *
   * @param string $keys
   *   The fulltext search keywords to place on the query.
   * @param array $data
   *   (optional) Additional data passed to the callback.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The query that would normally be executed when $keys is entered as the
   *   keywords for this search. Callers should check whether keywords are
   *   actually set on the query.
   *
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   *   Thrown if the query couldn't be created.
   */
  public function createQuery($keys, array $data = []);

}
