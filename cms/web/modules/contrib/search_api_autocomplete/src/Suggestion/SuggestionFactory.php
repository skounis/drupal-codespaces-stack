<?php

namespace Drupal\search_api_autocomplete\Suggestion;

use Drupal\Core\Url;

/**
 * Provides factory methods for simpler creation of autocomplete suggestions.
 *
 * @see \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface
 */
class SuggestionFactory {

  /**
   * The keywords input by the user so far.
   *
   * @var string|null
   */
  protected $userInput;

  /**
   * Constructs a SuggestionFactory object.
   *
   * @param string|null $user_input
   *   (optional) The keywords input by the user so far.
   */
  public function __construct($user_input = NULL) {
    $this->userInput = $user_input;
  }

  /**
   * Creates a suggestion based on the suggested keywords.
   *
   * @param string $suggested_keys
   *   The suggested keywords.
   * @param int|null $results_count
   *   (optional) The estimated number of results.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface
   *   An autocomplete suggestion.
   */
  public function createFromSuggestedKeys($suggested_keys, $results_count = NULL) {
    $suggestion = new Suggestion($suggested_keys);

    $lowercase_input = mb_strtolower($this->userInput);
    $lowercase_keys = mb_strtolower($suggested_keys);
    $start_position = mb_strpos($lowercase_keys, $lowercase_input);
    if ($start_position === FALSE) {
      $suggestion->setLabel($suggested_keys);
    }
    else {
      if ($start_position) {
        $prefix = mb_substr($suggested_keys, 0, $start_position);
        $suggestion->setSuggestionPrefix($prefix);
      }
      $input_length = mb_strlen($this->userInput);
      $end_position = $start_position + $input_length;
      if ($end_position < mb_strlen($suggested_keys)) {
        $suffix = mb_substr($suggested_keys, $end_position);
        $suggestion->setSuggestionSuffix($suffix);
      }
      $suggestion->setUserInput(mb_substr($suggested_keys, $start_position, $input_length));
    }

    if ($results_count !== NULL) {
      $suggestion->setResultsCount($results_count);
    }

    return $suggestion;
  }

  /**
   * Creates a suggestion from a suggested suffix to the user input.
   *
   * @param string $suggestion_suffix
   *   The suggestion suffix.
   * @param int|null $results_count
   *   (optional) The estimated number of results.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface
   *   An autocomplete suggestion.
   */
  public function createFromSuggestionSuffix($suggestion_suffix, $results_count = NULL) {
    $suggestion = new Suggestion();

    $suggestion->setUserInput($this->userInput);
    $suggestion->setSuggestionSuffix($suggestion_suffix);
    if ($results_count !== NULL) {
      $suggestion->setResultsCount($results_count);
    }

    return $suggestion;
  }

  /**
   * Creates a suggestion that redirects to the specified URL.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to which this suggestion should redirect.
   * @param string|null $label
   *   (optional) The label to set for the suggestion. Only makes sense if
   *   $render isn't given.
   * @param array|null $render
   *   (optional) The render array that should be displayed for this suggestion.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface
   *   An autocomplete suggestion.
   */
  public function createUrlSuggestion(Url $url, $label = NULL, ?array $render = NULL) {
    $suggestion = new Suggestion(NULL, $url);

    if ($label !== NULL) {
      $suggestion->setLabel($label);
    }
    if ($render !== NULL) {
      $suggestion->setRender($render);
    }

    return $suggestion;
  }

}
