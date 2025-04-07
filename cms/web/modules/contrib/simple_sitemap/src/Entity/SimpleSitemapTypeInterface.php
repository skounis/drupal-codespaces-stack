<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface;

/**
 * Provides an interface defining a sitemap type entity.
 */
interface SimpleSitemapTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the sitemap generator.
   *
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface
   *   The sitemap generator.
   */
  public function getSitemapGenerator(): SitemapGeneratorInterface;

  /**
   * Gets the URL generators.
   *
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorInterface[]
   *   Array of URL generators.
   */
  public function getUrlGenerators(): array;

  /**
   * Determines whether the sitemap type has a URL generator with the given ID.
   *
   * @param string $generator_id
   *   ID of the URL generator.
   *
   * @return bool
   *   TRUE if the sitemap type has a URL generator with the given ID.
   */
  public function hasUrlGenerator(string $generator_id): bool;

}
