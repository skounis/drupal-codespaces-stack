<?php

namespace Drupal\sitemap\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Sitemap annotation object.
 *
 * @Annotation
 *
 * @see \Drupal\sitemap\SitemapManager
 * @see \Drupal\sitemap\SitemapInterface
 *
 * @ingroup sitemap
 */
class Sitemap extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * A short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Sets the weight of this item relative to other items in the sitemap.
   *
   * @var int
   */
  public $weight = NULL;

  /**
   * Whether this plugin is enabled or disabled by default.
   *
   * @var bool
   */
  public $enabled = FALSE;

  /**
   * The default settings for the plugin.
   *
   * @var array
   */
  public $settings = [];

}
