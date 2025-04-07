<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides an interface for Simple XML Sitemap plugins.
 */
interface SimpleSitemapPluginInterface extends ContainerFactoryPluginInterface, PluginInspectionInterface {

  /**
   * Gets the label of this plugin.
   *
   * @return string
   *   The label of this plugin.
   */
  public function label(): string;

  /**
   * Gets the description of this plugin.
   *
   * @return string
   *   The description of this plugin.
   */
  public function description(): string;

  /**
   * Gets the settings of this plugin.
   *
   * @return array
   *   The settings of this plugin.
   */
  public function settings(): array;

}
