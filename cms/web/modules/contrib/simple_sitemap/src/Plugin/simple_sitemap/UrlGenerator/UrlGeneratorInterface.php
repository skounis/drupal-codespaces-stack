<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginInterface;

/**
 * Provides an interface for UrlGenerator plugins.
 */
interface UrlGeneratorInterface extends SimpleSitemapPluginInterface {

  /**
   * Sets the sitemap.
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $sitemap
   *   The sitemap entity to set.
   *
   * @return $this
   */
  public function setSitemap(SimpleSitemapInterface $sitemap): UrlGeneratorInterface;

  /**
   * Gets the datasets.
   *
   * @return array
   *   The datasets.
   */
  public function getDataSets(): array;

  /**
   * Generates URLs from specified dataset.
   *
   * @param mixed $data_set
   *   The dataset to process.
   *
   * @return array
   *   Generation result.
   */
  public function generate($data_set): array;

}
