<?php

namespace Drupal\search_api_autocomplete\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an autocompletion search.
 *
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginInterface
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginManager
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class SearchApiAutocompleteSearch extends Plugin {

  /**
   * The search label.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The search description.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The search's group label.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $group_label = NULL;

  /**
   * The search's group's description.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $group_description = NULL;

  /**
   * The search's index ID.
   *
   * @var string
   */
  public $index;

}
