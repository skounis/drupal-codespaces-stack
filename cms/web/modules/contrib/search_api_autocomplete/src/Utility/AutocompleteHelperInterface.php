<?php

namespace Drupal\search_api_autocomplete\Utility;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Provides an interface for the autocomplete helper service.
 */
interface AutocompleteHelperInterface extends AccessInterface {

  /**
   * Splits a string with search keywords into two parts.
   *
   * The first part consists of all words the user has typed completely, the
   * second one contains the beginning of the last, possibly incomplete word.
   *
   * @param string $keys
   *   The passed in keys.
   *
   * @return string[]
   *   An array with $keys split into exactly two parts, both of which may be
   *   empty.
   */
  public function splitKeys($keys);

  /**
   * Alters a textfield form element to use autocompletion.
   *
   * @param array $element
   *   The altered element.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   The autocomplete search.
   * @param array $data
   *   (optional) Additional data to pass to the autocomplete callback.
   */
  public function alterElement(array &$element, SearchInterface $search, array $data = []);

  /**
   * Checks access to the autocompletion route.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search_api_autocomplete_search
   *   The configured autocompletion search.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SearchInterface $search_api_autocomplete_search, AccountInterface $account);

}
