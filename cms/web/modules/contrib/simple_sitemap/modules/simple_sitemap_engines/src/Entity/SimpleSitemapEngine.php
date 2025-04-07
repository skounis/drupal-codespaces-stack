<?php

namespace Drupal\simple_sitemap_engines\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the search engine entity class.
 *
 * @ConfigEntityType(
 *   id = "simple_sitemap_engine",
 *   label = @Translation("Search engine"),
 *   admin_permission = "administer sitemap settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   handlers = {
 *     "storage" = "\Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngineStorage",
 *     "list_builder" = "Drupal\simple_sitemap_engines\SearchEngineListBuilder",
 *   },
 *   links = {
 *     "collection" = "/admin/config/search/simplesitemap/engines/list",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "index_now_url",
 *     "sitemap_variants",
 *   }
 * )
 */
class SimpleSitemapEngine extends ConfigEntityBase {

  /**
   * The search engine ID.
   *
   * @var string
   */
  public $id;

  /**
   * The search engine label.
   *
   * @var string
   */
  public $label;

  /**
   * The sitemap submission URL.
   *
   * When submitting to search engines, '[sitemap]' will be replaced with the
   * full URL to the sitemap.xml.
   *
   * @var string|null
   */
  public $url;

  /**
   * List of sitemaps to be submitted to this search engine.
   *
   * @var array
   */
  public $sitemap_variants;

  /**
   * The IndexNow submission URL.
   *
   * When submitting to search engines, '[key]' and '[url]' will be replaced
   * with the respective values.
   *
   * @var string|null
   */
  public $index_now_url;

  /**
   * Implementation of the magic __toString() method.
   *
   * @return string
   *   The search engine label.
   */
  public function __toString() {
    return (string) $this->label();
  }

  /**
   * Whether the engine accepts sitemap submissions.
   *
   * @return bool
   *   True if the engine accepts sitemap submissions.
   */
  public function hasSitemapSubmission() {
    return (bool) $this->url;
  }

  /**
   * Whether the engine accepts IndexNow submissions.
   *
   * @return bool
   *   True if the engine accepts IndexNow submissions.
   */
  public function hasIndexNow() {
    return (bool) $this->index_now_url;
  }

  /**
   * Loads all engines capable of sitemap pinging.
   *
   * @return \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine[]
   *   Engines capable of sitemap pinging.
   */
  public static function loadSitemapSubmissionEngines(): array {
    $ids = \Drupal::entityQuery('simple_sitemap_engine')
      ->exists('url')
      ->execute();

    return static::loadMultiple($ids);
  }

  /**
   * Loads all IndexNow capable engines.
   *
   * @return \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine[]
   *   IndexNow capable engines.
   */
  public static function loadIndexNowEngines(): array {
    $ids = \Drupal::entityQuery('simple_sitemap_engine')
      ->exists('index_now_url')
      ->execute();

    return static::loadMultiple($ids);
  }

  /**
   * Loads a random IndexNow capable engine.
   *
   * @return \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine|null
   *   Random IndexNow capable engine or NULL if none given.
   */
  public static function loadRandomIndexNowEngine(): ?SimpleSitemapEngine {
    if ($ids = \Drupal::entityQuery('simple_sitemap_engine')
      ->exists('index_now_url')
      ->execute()) {
      return static::load(array_rand($ids));
    }

    return NULL;
  }

}
