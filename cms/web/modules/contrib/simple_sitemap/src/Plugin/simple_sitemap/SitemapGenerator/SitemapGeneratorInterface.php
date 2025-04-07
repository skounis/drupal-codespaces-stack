<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginInterface;

/**
 * Provides an interface for SitemapGenerator plugins.
 */
interface SitemapGeneratorInterface extends SimpleSitemapPluginInterface {

  /**
   * Sets the sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $sitemap
   *   The sitemap entity to set.
   *
   * @return $this
   */
  public function setSitemap(SimpleSitemapInterface $sitemap): SitemapGeneratorInterface;

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   * @return string
   *   Sitemap chunk.
   */
  public function getChunkContent(array $links): string;

  /**
   * Generates and returns a sitemap index.
   *
   * @return string
   *   Sitemap index.
   */
  public function getIndexContent(): string;

  /**
   * Generates and returns sitemap XSL.
   *
   * @return string|null
   *   Sitemap XSL.
   */
  public function getXslContent(): ?string;

}
