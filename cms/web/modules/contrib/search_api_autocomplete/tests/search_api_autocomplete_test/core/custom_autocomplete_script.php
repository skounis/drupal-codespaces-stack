<?php

/**
 * @file
 * Outputs some autocomplete suggestions as JSON, for testing purposes.
 *
 * Contained within a /core/ sub-directory to trick Drupal's .htaccess file.
 *
 * The JSON output of this file is a single array of suggestion list items, with
 * each being an object with the following keys:
 * - value: The keywords which should be entered into the search field for this
 *   suggestion. For returning a suggestion that will redirect to a URL instead
 *   of entering keywords, use the URL here proceeded by a single leading space
 *   character.
 * - label: HTML which should be displayed for the suggestion.
 */

search_api_autocomplete_test_custom_autocomplete_callback();

/**
 * Outputs some autocomplete suggestions as JSON, for testing purposes.
 */
function search_api_autocomplete_test_custom_autocomplete_callback() {
  $suggestions = [];
  foreach ($_GET as $key => $value) {
    $suggestions[] = [
      'value' => $value,
      'label' => htmlentities("$key: $value"),
    ];
  }
  header('Content-type: application/json');
  echo json_encode($suggestions, JSON_PRETTY_PRINT);
  exit;
}
