<?php

namespace Drupal\simple_sitemap_engines\Submitter;

use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine;

/**
 * Sitemap submitting service.
 */
class SitemapSubmitter extends SubmitterBase {

  /**
   * ID of search engine to be submitted to.
   *
   * @var string
   */
  protected $engineId;

  /**
   * Currently processed sitemap variant.
   *
   * @var string
   */
  protected $currentVariant;

  /**
   * {@inheritdoc}
   */
  protected function onSuccess(): void {
    $this->logger->m('Sitemap @variant submitted to @url', [
      '@variant' => $this->currentVariant,
      '@url' => $this->url,
    ])->log();

    $this->state->set(
      "simple_sitemap_engines.simple_sitemap_engine.{$this->engineId}.last_submitted",
      $this->time->getRequestTime()
    );
  }

  /**
   * Sends sitemap URLs to a specific engine.
   *
   * @param string $engine_id
   *   ID of search engine to be submitted to.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function process(string $engine_id) {
    $this->engineId = $engine_id;
    if ($engine = SimpleSitemapEngine::load($this->engineId)) {
      // Submit all variants that are enabled for this search engine.
      foreach (SimpleSitemap::loadMultiple($engine->sitemap_variants) as $variant => $sitemap) {
        if ($sitemap->status()) {
          $this->currentVariant = $variant;
          $url = str_replace('[sitemap]', $sitemap->toUrl()->toString(), $engine->url);
          $this->request($url);
        }
      }
    }
  }

}
