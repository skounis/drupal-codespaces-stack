<?php

namespace Drupal\search_api_autocomplete\Utility;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api_autocomplete\Element\SearchApiAutocomplete as AutocompleteElement;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Provides helper methods for creating autocomplete suggestions.
 */
class AutocompleteHelper implements AutocompleteHelperInterface {

  /**
   * Constructs a new class instance.
   */
  public function __construct() {
    if (func_get_args()) {
      @trigger_error('Constructing \Drupal\search_api_autocomplete\Utility\AutocompleteHelper with any parameters is deprecated in search_api_autocomplete:8.x-1.10 and will stop working in search_api_autocomplete:2.0.0. See https://www.drupal.org/node/3487349', E_USER_DEPRECATED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function splitKeys($keys) {
    $keys = ltrim($keys);
    // If there is whitespace or a quote on the right, all words have been
    // completed.
    if (rtrim($keys, " \"") != $keys) {
      return [rtrim($keys, ' '), ''];
    }
    if (preg_match('/^(.*?)\s*"?([\S]*)$/', $keys, $m)) {
      return [$m[1], $m[2]];
    }
    return ['', $keys];
  }

  /**
   * {@inheritdoc}
   */
  public function alterElement(array &$element, SearchInterface $search, array $data = []) {
    $element['#type'] = 'search_api_autocomplete';
    $element['#search_id'] = $search->id();
    $element['#additional_data'] = $data;

    // If the element already has the "#process" key set, the default callbacks
    // (including our own processSearchApiAutocomplete() callback) will not be
    // added anymore. Make sure that processSearchApiAutocomplete() will still
    // be called.
    if (isset($element['#process'])) {
      array_unshift($element['#process'], [AutocompleteElement::class, 'processSearchApiAutocomplete']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access(SearchInterface $search_api_autocomplete_search, AccountInterface $account) {
    $search = $search_api_autocomplete_search;
    $permission = 'use search_api_autocomplete for ' . $search->id();
    $access = AccessResult::allowedIf($search->status())
      ->andIf(AccessResult::allowedIf($search->hasValidIndex() && $search->getIndex()->status()))
      ->andIf(AccessResult::allowedIfHasPermissions($account, [$permission, 'administer search_api_autocomplete'], 'OR'))
      ->addCacheableDependency($search);
    if ($access instanceof AccessResultReasonInterface) {
      $access->setReason("The \"$permission\" permission is required and autocomplete for this search must be enabled.");
    }
    return $access;
  }

}
