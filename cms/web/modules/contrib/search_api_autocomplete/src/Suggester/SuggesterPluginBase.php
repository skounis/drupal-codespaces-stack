<?php

namespace Drupal\search_api_autocomplete\Suggester;

use Drupal\search_api_autocomplete\Plugin\PluginBase;
use Drupal\search_api_autocomplete\SearchInterface;

/**
 * Provides a base class for suggester plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. The definition includes the following keys:
 * - id: The unique, system-wide identifier of the suggester plugin.
 * - label: The human-readable name of the suggester plugin, translated.
 * - description: A human-readable description for the suggester plugin,
 *   translated.
 * - default_weight: (optional) The default weight to assign to the suggester.
 *   Defaults to 0.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiAutocompleteSuggester(
 *   id = "my_suggester",
 *   label = @Translation("My Suggester"),
 *   description = @Translation("Creates suggestions based on internet memes."),
 *   default_weight = -10,
 * )
 * @endcode
 *
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSuggester
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterInterface
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterManager
 * @see plugin_api
 * @see hook_search_api_autocomplete_suggester_info_alter()
 */
abstract class SuggesterPluginBase extends PluginBase implements SuggesterInterface {

  /**
   * {@inheritdoc}
   */
  public function alterAutocompleteElement(array &$element) {}

  /**
   * {@inheritdoc}
   */
  public static function supportsSearch(SearchInterface $search) {
    return TRUE;
  }

}
