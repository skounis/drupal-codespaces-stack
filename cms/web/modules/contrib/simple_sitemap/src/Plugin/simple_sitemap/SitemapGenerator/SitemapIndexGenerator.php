<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

/**
 * Provides the sitemap index generator.
 *
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator
 *
 * @SitemapGenerator(
 *   id = "index",
 *   label = @Translation("Sitemap index generator"),
 *   description = @Translation("Generates an index of your sitemaps."),
 * )
 */
class SitemapIndexGenerator extends DefaultSitemapGenerator {

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   * @return string
   *   Sitemap chunk
   */
  public function getChunkContent(array $links): string {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    $this->addXslUrl();
    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');
    $this->addSitemapAttributes();
    $this->addLinks($links);
    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

  /**
   * {@inheritdoc}
   */
  protected function addLinks(array $links): void {
    foreach ($links as $url_data) {
      $this->writer->startElement('sitemap');

      if (isset($url_data['url'])) {
        $this->writer->writeElement('loc', $url_data['url']);
      }

      if (isset($url_data['lastmod'])) {
        $this->writer->writeElement('lastmod', $url_data['lastmod']);
      }

      $this->writer->endElement();
    }
  }

}
