<?php

/**
 * @file
 * Script to regenerate test fixtures.
 */

require_once __DIR__ . '/../tests/modules/project_browser_test/src/DrupalOrgClientMiddleware.php';

use Drupal\project_browser_test\DrupalOrgClientMiddleware;

/**
 * Drupal.org domain.
 *
 * @var string
 */
const DRUPAL_ORG = 'https://www.drupal.org';

/**
 * Folder where to keep the fixtures.
 *
 * @var string
 */
const DESTINATION_FOLDER = __DIR__ . '/../tests/fixtures/drupalorg_jsonapi/';

/**
 * Map of fixture file that uses categories in the query.
 *
 * @var array
 */
const QUERY_CATEGORIES_MAP = [
  'filters2.json' => ['Developer tools', 'E-commerce'],
  'filters3.json' => ['E-commerce'],
  'filters4.json' => ['Developer tools', 'E-commerce'],
  'filters5.json' => ['E-commerce'],
  'filters6.json' => ['Media'],
  'filters7.json' => ['Developer tools', 'Media'],
  'pager2.json' => ['Accessibility'],
  'pager3.json' => ['Accessibility', 'E-commerce'],
  'pager4.json' => ['Accessibility', 'E-commerce', 'Media'],
];

/**
 * Map of filter name in the URL to filter key.
 *
 * @var array
 */
const FILTERS_MAP = [
  'filter[maintenance_status_uuid][value]' => 'maintained',
  'filter[development_status_uuid][value]' => 'active',
];

/**
 * Gets the URL contents.
 *
 * @param string $url
 *   URL to query.
 */
function get_url($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Curl.ProjectBrowser');
  curl_setopt($ch, CURLOPT_URL, $url);
  $contents = curl_exec($ch);

  curl_close($ch);

  return $contents;
}

/**
 * Gets the dynamic UUIDs of the filters from the endpoint.
 */
function gather_filters_uuids() {
  $filters = [];

  $contents = get_url(DRUPAL_ORG . '/drupalorg-api/project-browser-filters');
  if ($contents) {
    $filters = json_decode($contents, TRUE);
  }

  return $filters;
}

/**
 * Gets the dynamic UUIDs of the filters from the endpoint.
 */
function gather_categories_uuids() {
  $categories = [];

  $contents = get_url(DRUPAL_ORG . '/jsonapi/taxonomy_term/module_categories');
  if ($contents) {
    $categories = json_decode($contents, TRUE);
    if (!empty($categories['data'])) {
      $tmp = [];
      foreach ($categories['data'] as $entry) {
        $tmp[$entry['attributes']['name']] = [
          'id' => $entry['id'],
          'name' => $entry['attributes']['name'],
        ];
      }

      $categories = $tmp;
    }
  }

  return $categories;
}

/**
 * Replace old UUIDs with new UUIDs.
 *
 * @param string $filters
 *   Filters with query format.
 * @param string $fixture_file_name
 *   Filename of the fixture so we can map the categories.
 */
function replace_filters($filters, $fixture_file_name = NULL) {
  global $_project_browser_fix_suggestions;
  $filters = urldecode($filters);

  // Remove semver query as it is core version dependent.
  $filters = preg_replace('/filter\[core_semver_(.*?)\]\[(.*?)\]=(.*?)&/', '', $filters);

  // Make them an array for easier manipulation.
  $filters = explode('&', $filters);

  // Categories.
  if ($fixture_file_name) {
    if (in_array($fixture_file_name, array_keys(QUERY_CATEGORIES_MAP))) {
      $new_categories = gather_categories_uuids();
      $category_keys = QUERY_CATEGORIES_MAP[$fixture_file_name];
      $replaced = 0;
      foreach ($filters as &$filter) {
        if (str_contains($filter, '[module_categories_uuid][value]')) {
          $filter_parts = explode('=', $filter);
          $previous = $filter_parts[1];
          $filter_parts[1] = $new_categories[$category_keys[$replaced]]['id'];
          $filter = implode('=', $filter_parts);
          $replaced++;
          if ($previous !== $filter_parts[1]) {
            $_project_browser_fix_suggestions[$previous] = $filter_parts[1];
          }
        }
      }
      if (count($category_keys) !== $replaced) {
        echo "[ERROR] Map doesn't match filters for $fixture_file_name" . PHP_EOL;
      }
    }
  }

  // Maintenance and development status.
  $new_filters = gather_filters_uuids();
  foreach ($filters as &$filter) {
    // Check to see if the filters have any of the keys defined in the map.
    foreach (FILTERS_MAP as $map_key => $map_value) {
      if (str_contains($filter, $map_key)) {
        $filter_parts = explode('=', $filter);
        // Try to extract the index from the filter key.
        // eg: filter[development_status_uuid][value][1] should produce [1].
        $index = str_replace($map_key, '', $filter_parts[0]);
        if (!empty($index)) {
          $index = trim($index, '[]');
          // See if there is a mapping for that part and replace the value.
          $previous = $filter_parts[1];
          $filter_parts[1] = $new_filters[$map_value][$index] ?? FALSE;
          if ($filter_parts[1] !== FALSE) {
            // Put together the filter again.
            $filter = implode('=', $filter_parts);
            if ($previous !== $filter_parts[1]) {
              $_project_browser_fix_suggestions[$previous] = $filter_parts[1];
            }
          }
        }
      }
    }
  }

  // Return the string in the same format that it was passed to the function.
  $filters = implode('&', $filters);
  // Encode the possible spaces so as not to break the URL.
  $filters = str_replace(' ', '%20', $filters);
  return $filters;
}

/**
 * Generate the fixtures requested.
 *
 * @param array $map
 *   Map of path and destination file.
 * @param string $endpoint_base_url
 *   Base URL of the endpoint.
 * @param bool $replace_filters
 *   Replace filters with new values coming from the endpoint.
 */
function generate_fixtures($map, $endpoint_base_url, $replace_filters = FALSE) {
  foreach ($map as $jsonapi_path => $fixture_file_name) {
    if ($replace_filters) {
      $jsonapi_path_parts = explode('?', $jsonapi_path);
      if (count($jsonapi_path_parts) == 2) {
        $jsonapi_path_parts[1] = replace_filters($jsonapi_path_parts[1], $fixture_file_name);
      }
      $jsonapi_path = implode('?', $jsonapi_path_parts);
    }

    // Wait between requests as otherwise they could be blocked.
    sleep(1);
    $contents = get_url($endpoint_base_url . $jsonapi_path);
    if ($contents) {
      file_put_contents(DESTINATION_FOLDER . $fixture_file_name, $contents);
      echo "[OK] Saving $fixture_file_name" . PHP_EOL;
    }
    else {
      echo "[WARNING] Empty response for $fixture_file_name" . PHP_EOL;
    }
  }
}

// Begin script.
$_project_browser_fix_suggestions = [];

// Make sure the folder exists.
if (!is_dir(DESTINATION_FOLDER)) {
  mkdir(rtrim(DESTINATION_FOLDER, '/'));
}

if (!class_exists('Drupal')) {
  /**
   * Fake the Drupal class if the script is used outside of Drupal.
   */
  // phpcs:ignore
  class Drupal {
    const VERSION = '11.x';

  }
}

// Generate the fixtures for both the json:api and non json:api paths.
generate_fixtures(DrupalOrgClientMiddleware::DRUPALORG_JSONAPI_ENDPOINT_TO_FIXTURE_MAP, DRUPAL_ORG . '/jsonapi', TRUE);
generate_fixtures(DrupalOrgClientMiddleware::DRUPALORG_ENDPOINT_TO_FIXTURE_MAP, DRUPAL_ORG);

if (count($_project_browser_fix_suggestions)) {
  echo "Suggestions: " . PHP_EOL;
  foreach ($_project_browser_fix_suggestions as $old_key => $new_key) {
    echo "====> Replace $old_key with $new_key in the tests" . PHP_EOL;
  }
}
