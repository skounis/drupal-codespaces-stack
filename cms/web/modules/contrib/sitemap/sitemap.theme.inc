<?php

/**
 * @file
 * Sitemap theme preprocessors.
 */

use Drupal\Component\Utility\Html;

/**
 * Implements hook_preprocess_HOOK().
 */
function template_preprocess_sitemap_item(&$variables) {
  $variables['plugin_type'] = '';
  $variables['plugin_id'] = '';

  if (isset($variables['sitemap'])) {
    if ($id = $variables['sitemap']->getPluginDefinition()['id']) {
      $filter = [
        ' ' => '-',
        '_' => '-',
        '/' => '-',
        '[' => '-',
        ']' => '-',
        ':' => '-',
      ];
      $variables['plugin_type'] = Html::cleanCssIdentifier($id, $filter);
      $variables['plugin_id'] = Html::cleanCssIdentifier($variables['sitemap']->getPluginId(), $filter);
    }
  }
}

/**
 * Preprocess variables for sitemap-taxonomy-term.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - name: The name of the taxonomy term.
 *   - url: The URL to the taxonomy term.
 *   - show_count: Whether or not to display the number of nodes using the term.
 *   - count: The number of nodes using this taxonomy term.
 *   - show_feed: Whether or not to display the RSS feed link for this term.
 *   - feed: The URL to the taxonomy term's feed.
 */
function template_preprocess_sitemap_taxonomy_term(array &$variables) {
  if ($variables['show_feed'] && $variables['feed']) {
    $variables['feed_icon'] = _sitemap_rss_feed_icon();
  }
}

/**
 * Preprocess variables for sitemap-frontpage-item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - text: The text for the front page link.
 *   - url: The URL to the front page.
 *   - feed: The URL to the site feed.
 */
function template_preprocess_sitemap_frontpage_item(array &$variables) {
  if (!empty($variables['feed'])) {
    $variables['feed_icon'] = _sitemap_rss_feed_icon();
  }
}

/**
 * Helper function to create the RSS feed icon render array.
 *
 * @return array
 *   Returns a render array.
 */
function _sitemap_rss_feed_icon() {
  return [
    '#theme' => 'image',
    '#uri' => \Drupal::service('extension.list.module')->getPath('sitemap') . '/images/feed-small.png',
    '#alt' => t('RSS feed icon'),
  ];
}
