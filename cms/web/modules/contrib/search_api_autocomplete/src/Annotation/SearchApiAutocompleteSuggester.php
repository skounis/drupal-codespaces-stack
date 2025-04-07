<?php

namespace Drupal\search_api_autocomplete\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a autocomplete suggester plugin.
 *
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterInterface
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterManager
 * @see \Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class SearchApiAutocompleteSuggester extends Plugin {

  /**
   * The plugin label.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The plugin description.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The default weight for this suggester.
   *
   * @var int
   */
  public $default_weight = 0;

}
