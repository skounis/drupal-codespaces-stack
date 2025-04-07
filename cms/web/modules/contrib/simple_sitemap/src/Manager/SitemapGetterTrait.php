<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\simple_sitemap\Entity\SimpleSitemap;

/**
 * Provides a helper to setting/getting sitemaps.
 */
trait SitemapGetterTrait {

  /**
   * The currently set sitemaps.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   */
  protected $sitemaps;

  /**
   * Sets the variants.
   *
   * @param array|string|null $variants
   *   array: Array of variants to be set.
   *   string: A particular variant to be set.
   *   null: All compatible variants will be set.
   *
   * @return $this
   *
   * @deprecated in simple_sitemap:4.1.7 and is removed from simple_sitemap:5.0.0.
   *    Use setSitemaps() instead.
   * @see https://www.drupal.org/project/simple_sitemap/issues/3375932
   */
  public function setVariants($variants = NULL): self {
    return $this->setSitemaps($variants);
  }

  /**
   * {@inheritdoc}
   */
  public function setSitemaps($sitemaps = NULL): self {
    if ($sitemaps === NULL) {
      $this->sitemaps = static::getCompatibleSitemaps();
    }
    else {
      // Not casting to array here directly, because $sitemaps could be an
      // object.
      $sitemaps = !is_array($sitemaps) ? [$sitemaps] : $sitemaps;

      if ($sitemaps && !($sitemaps[array_key_first($sitemaps)] instanceof SimpleSitemap)) {
        $sitemaps = SimpleSitemap::loadMultiple($sitemaps);
      }
      else {
        // Make sure the array keys are sitemap IDs.
        foreach ($sitemaps as $sitemap) {
          $sitemaps_by_id[$sitemap->id()] = $sitemap;
        }
      }
      $this->sitemaps = $sitemaps_by_id ?? $sitemaps;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSitemaps(): array {
    if (NULL === $this->sitemaps) {
      $this->setSitemaps();
    }

    return $this->sitemaps;
  }

}
