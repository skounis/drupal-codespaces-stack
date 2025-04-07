<?php

namespace Drupal\search_api_autocomplete\Suggestion;

use Drupal\Core\Url;

/**
 * Provides a value object meant to be used as result of suggestions.
 */
class Suggestion implements SuggestionInterface {

  /**
   * The keywords this suggestion will autocomplete to.
   *
   * @var string|null
   */
  protected $suggestedKeys;

  /**
   * A URL to which the suggestion should redirect.
   *
   * @var \Drupal\Core\Url|null
   */
  protected $url;

  /**
   * For special suggestions, some kind of HTML prefix describing them.
   *
   * @var string|null
   */
  protected $prefix;

  /**
   * The label to use for the suggestion.
   *
   * @var string|null
   */
  protected $label;

  /**
   * A suggested prefix for the entered input.
   *
   * @var string|null
   */
  protected $suggestionPrefix;

  /**
   * The input entered by the user. Defaults to $user_input.
   *
   * @var string|null
   */
  protected $userInput;

  /**
   * A suggested suffix for the entered input.
   *
   * @var string|null
   */
  protected $suggestionSuffix;

  /**
   * If available, the estimated number of results for these keys.
   *
   * @var int|null
   */
  protected $resultsCount;

  /**
   * If given, an HTML string or render array.
   *
   * @var array|null
   */
  protected $render;

  /**
   * Constructs a Suggestion object.
   *
   * @param string|null $suggested_keys
   *   (optional) The suggested keys.
   * @param \Drupal\Core\Url|null $url
   *   (optional) The URL to redirect to.
   * @param string|null $prefix
   *   (optional) The prefix for the suggestion.
   * @param string|null $label
   *   (optional) The label for the suggestion.
   * @param string|null $suggestion_prefix
   *   (optional) The suggested prefix.
   * @param string|null $user_input
   *   (optional) The user input.
   * @param string|null $suggestion_suffix
   *   (optional) The suggested suffix.
   * @param int|null $results_count
   *   (optional) The estimated number of results.
   * @param array|null $render
   *   (optional) The render array.
   */
  public function __construct(
    $suggested_keys = NULL,
    ?Url $url = NULL,
    $prefix = NULL,
    $label = NULL,
    $suggestion_prefix = NULL,
    $user_input = NULL,
    $suggestion_suffix = NULL,
    $results_count = NULL,
    ?array $render = NULL,
  ) {
    $this->suggestedKeys = $suggested_keys;
    $this->url = $url;
    $this->prefix = $prefix;
    $this->label = $label;
    $this->suggestionPrefix = $suggestion_prefix;
    $this->userInput = $user_input;
    $this->suggestionSuffix = $suggestion_suffix;
    $this->resultsCount = $results_count;
    $this->render = $render;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestedKeys() {
    if ($this->url) {
      return NULL;
    }
    if ($this->suggestedKeys) {
      return $this->suggestedKeys;
    }
    return $this->suggestionPrefix . $this->userInput . $this->suggestionSuffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionPrefix() {
    return $this->suggestionPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInput() {
    return $this->userInput;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionSuffix() {
    return $this->suggestionSuffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getResultsCount() {
    return $this->resultsCount;
  }

  /**
   * {@inheritdoc}
   */
  public function getRender() {
    return $this->render;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggestedKeys($suggestedKeys) {
    $this->suggestedKeys = $suggestedKeys;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->url = $url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrefix($prefix) {
    $this->prefix = $prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggestionPrefix($suggestion_prefix) {
    $this->suggestionPrefix = $suggestion_prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserInput($user_input) {
    $this->userInput = $user_input;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSuggestionSuffix($suggestion_suffix) {
    $this->suggestionSuffix = $suggestion_suffix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setResultsCount($results) {
    $this->resultsCount = $results;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRender($render) {
    $this->render = $render;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    if (!empty($this->render)) {
      return $this->render;
    }
    return [
      '#theme' => 'search_api_autocomplete_suggestion',
      '#keys' => $this->getSuggestedKeys(),
      '#url' => $this->getUrl(),
      '#note' => $this->getPrefix(),
      '#label' => $this->getLabel(),
      '#results_count' => $this->getResultsCount(),
      '#suggestion_prefix' => $this->getSuggestionPrefix(),
      '#suggestion_suffix' => $this->getSuggestionSuffix(),
      '#user_input' => $this->getUserInput(),
    ];
  }

}
