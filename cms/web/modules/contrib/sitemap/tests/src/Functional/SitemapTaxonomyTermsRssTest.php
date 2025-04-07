<?php

namespace Drupal\Tests\sitemap\Functional;

/**
 * Tests the display of RSS links based on sitemap settings.
 *
 * @group sitemap
 */
class SitemapTaxonomyTermsRssTest extends SitemapTaxonomyTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $vocab = $this->vocabulary;
    $vid = $vocab->id();

    // Show all taxonomy terms, even if they are not assigned to any nodes.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => 0]);
  }

  /**
   * Tests included RSS links.
   */
  public function testIncludeRssLinks() {
    // The vocabulary is already configured to display in parent ::setUp().
    $vocab = $this->vocabulary;
    $vid = $vocab->id();

    // Create terms.
    $this->terms = $this->createTerms($vocab);

    // Assert that RSS links for terms are not included in the sitemap.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Include an RSS link for all terms.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][enable_rss]" => TRUE]);

    // Assert that RSS links are included in the sitemap.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Change RSS feed depth to 0.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 0]);

    // Assert that RSS links are not included in the sitemap.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $term->id() . '/feed');
    }

  }

  /**
   * Tests RSS feed depth.
   */
  public function testRssFeedDepth() {
    // The vocabulary is already configured to display in parent ::setUp().
    $vocab = $this->vocabulary;
    $vid = $vocab->id();

    // Create terms.
    $this->terms = $this->createNestedTerms($vocab);

    // Assert that RSS links are not included by default.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Set RSS feed depth to maximum.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][enable_rss]" => TRUE]);
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 9]);

    // Assert that all RSS links are included in the sitemap.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Change RSS feed depth to 0.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 0]);

    // Assert that RSS links are not included in the sitemap.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Change RSS feed depth to 1.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 1]);

    // Assert that only RSS feed link for term 1 is included in the sitemap.
    $this->drupalGet('sitemap');
    $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $this->terms[0]->id() . '/feed');
    $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $this->terms[1]->id() . '/feed');
    $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $this->terms[2]->id() . '/feed');

    // Change RSS feed depth to 2.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 2]);

    // Assert that RSS feed link for term 1 and term 2 is included in the site
    // map.
    $this->drupalGet('sitemap');
    $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $this->terms[0]->id() . '/feed');
    $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $this->terms[1]->id() . '/feed');
    $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $this->terms[2]->id() . '/feed');

    // Change RSS feed depth to 3.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 3]);

    // Assert that all RSS links are included in the sitemap.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefExists('/taxonomy/term/' . $term->id() . '/feed');
    }

    // Change disable RSS links.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][enable_rss]" => FALSE]);
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][rss_depth]" => 9]);

    // Assert that RSS links are not included in the sitemap.
    $this->drupalGet('sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkByHrefNotExists('/taxonomy/term/' . $term->id() . '/feed');
    }

  }

  /* @todo Tests customized RSS links. */
  // Public function testCustomRssLinks() {
  // }.
}
