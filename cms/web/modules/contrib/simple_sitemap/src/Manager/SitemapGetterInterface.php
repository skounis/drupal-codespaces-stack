<?php

namespace Drupal\simple_sitemap\Manager;

/**
 * Provides an interface to setting/getting sitemaps.
 */
interface SitemapGetterInterface {

  /**
   * Gets the currently set sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   The currently set sitemaps, or all compatible sitemaps if none are set.
   */
  public function getSitemaps(): array;

  /**
   * Sets the sitemaps.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]|\Drupal\simple_sitemap\Entity\SimpleSitemapInterface|string[]|string|null $sitemaps
   *   SimpleSitemapInterface[]: Array of sitemap objects to be set.
   *   string[]: Array of sitemap IDs to be set.
   *   SimpleSitemapInterface: A particular sitemap object to be set.
   *   string: A particular sitemap ID to be set.
   *   null: All compatible sitemaps will be set.
   *
   * @return $this
   */
  public function setSitemaps($sitemaps = NULL): self;

}
