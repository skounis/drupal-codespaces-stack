<?php

namespace Drupal\search_api_autocomplete\Suggestion;

use Drupal\Core\Render\RenderableInterface;

/**
 * Defines a single autocompletion suggestion.
 */
interface SuggestionInterface extends RenderableInterface {

  /**
   * Retrieves the keywords this suggestion will autocomplete to.
   *
   * @return string|null
   *   The suggested keywords, or NULL if the suggestion should direct to a URL
   *   instead.
   */
  public function getSuggestedKeys();

  /**
   * Retrieves the URL to which the suggestion should redirect.
   *
   * A URL to which the suggestion should redirect instead of completing the
   * user input in the text field. This overrides the normal behavior and thus
   * makes the suggested keys obsolete.
   *
   * @return \Drupal\Core\Url|null
   *   The URL to which the suggestion should redirect to, or NULL if none was
   *   set.
   */
  public function getUrl();

  /**
   * Retrieves the prefix for the suggestion.
   *
   * For special kinds of suggestions, this will contain some kind of prefix
   * describing them.
   *
   * @return string|null
   *   The prefix, if set.
   */
  public function getPrefix();

  /**
   * Retrieves the label to use for the suggestion.
   *
   * Should only be used if the other fields that will be displayed (suggestion
   * prefix/suffix and user input) are empty.
   *
   * @return string
   *   The suggestion's label.
   */
  public function getLabel();

  /**
   * Retrieves the prefix suggested for the entered keys.
   *
   * @return string|null
   *   The suggested prefix, if any.
   */
  public function getSuggestionPrefix();

  /**
   * The input entered by the user, if it should be included in the label.
   *
   * @return string|null
   *   The input provided by the user.
   */
  public function getUserInput();

  /**
   * A suggested suffix for the entered input.
   *
   * @return string|null
   *   A suffix.
   */
  public function getSuggestionSuffix();

  /**
   * Returns the estimated number of results for this suggestion.
   *
   * @return int|null
   *   The estimated number of results, or NULL if no estimate is available.
   */
  public function getResultsCount();

  /**
   * Returns the render array set for this suggestion.
   *
   * This should be displayed to the user for this suggestion. If missing, the
   * suggestion is instead rendered with the
   * "search_api_autocomplete_suggestion" theme.
   *
   * @return array|null
   *   A renderable array of the suggestion results, or NULL if none was set.
   */
  public function getRender();

  /**
   * Sets the keys.
   *
   * @param string|null $keys
   *   The keys.
   *
   * @return $this
   */
  public function setSuggestedKeys($keys);

  /**
   * Sets the URL.
   *
   * @param \Drupal\Core\Url|null $url
   *   The URL.
   *
   * @return $this
   */
  public function setUrl($url);

  /**
   * Sets the prefix.
   *
   * @param string|null $prefix
   *   The prefix.
   *
   * @return $this
   */
  public function setPrefix($prefix);

  /**
   * Sets the label.
   *
   * @param string|null $label
   *   The new label.
   *
   * @return $this
   */
  public function setLabel($label);

  /**
   * Sets the suggestion prefix.
   *
   * @param string|null $suggestion_prefix
   *   The suggestion prefix.
   *
   * @return $this
   */
  public function setSuggestionPrefix($suggestion_prefix);

  /**
   * Sets the user input.
   *
   * @param string|null $user_input
   *   The user input.
   *
   * @return $this
   */
  public function setUserInput($user_input);

  /**
   * Sets the suggestion suffix.
   *
   * @param string|null $suggestion_suffix
   *   The suggestion suffix.
   *
   * @return $this
   */
  public function setSuggestionSuffix($suggestion_suffix);

  /**
   * Sets the result count.
   *
   * @param string|null $results
   *   The result count.
   *
   * @return $this
   */
  public function setResultsCount($results);

  /**
   * Sets the render array.
   *
   * @param array|null $render
   *   The render array.
   *
   * @return $this
   */
  public function setRender($render);

}
