<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\sitemap\Plugin\Sitemap\Vocabulary;

/**
 * Tests the display of taxonomies based on sitemap settings.
 *
 * @group sitemap
 */
class SitemapTaxonomyTermsTest extends SitemapTaxonomyTestBase {

  /**
   * Tests the term_threshold setting.
   */
  public function testTermThreshold() {
    // Create terms.
    $this->terms = $this->createTerms($this->vocabulary);

    // The vocabulary is already configured to display in parent ::setUp().
    $vocab = $this->vocabulary;
    $vid = $vocab->id();

    // Get term names from terms.
    $names = [];
    foreach ($this->terms as $term) {
      $names[] = $term->label();
    }

    // Confirm that terms without content are displayed by default.
    $this->drupalGet('/sitemap');
    foreach ($names as $term_name) {
      $this->assertSession()->pageTextContains($term_name);
    }

    // Create test node with terms.
    $this->createNodeWithTerms($this->terms);
    // @todo Figure out proper cache tags.
    drupal_flush_all_caches();

    // Require at least one node for taxonomy terms to show up.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => 1]);

    // Assert that terms with content are displayed on the sitemap as links.
    $this->drupalGet('sitemap');
    foreach ($names as $term_name) {
      $this->assertSession()->linkExists($term_name);
    }

    // Require at least two nodes for taxonomy terms to show up.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => 2]);

    $terms = $this->terms;
    unset($terms[0]);

    // Create a second test node with only two terms.
    $this->createNodeWithTerms($terms);

    $this->drupalGet('sitemap');
    $this->assertSession()->linkNotExists($this->terms[0]->label());
    $this->assertSession()->linkExists($this->terms[1]->label());
    $this->assertSession()->linkExists($this->terms[2]->label());

    // @todo Check for empty <li>s as well.
  }

  /**
   * Tests appearance of node counts.
   */
  public function testNodeCounts() {
    $this->terms = $this->createTerms($this->vocabulary);

    // The vocabulary is already configured to display in parent ::setUp().
    $vocab = $this->vocabulary;
    $vid = $vocab->id();

    // Create test node with terms.
    $this->createNodeWithTerms($this->terms);

    // Assert that node counts are not included in the sitemap by default.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary .count:contains('(1)')");
    $this->assertEquals(\count($elements), 0, 'Node counts not included.');

    // Configure module to display node counts.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][show_count]" => TRUE]);

    // Assert that node counts are included in the sitemap.
    $this->drupalGet('sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary .count:contains('(1)')");
    $this->assertEquals(\count($elements), 3, 'Node counts included.');

    // @todo Add another node and check counts.
    // @todo Test count display when parent term does not meet threshold.
  }

  /**
   * Tests vocabulary depth settings.
   */
  public function testVocabularyDepth() {
    // Create terms.
    $this->terms = $this->createNestedTerms($this->vocabulary);

    // Set to show all taxonomy terms, even if they are not assigned to content.
    $vid = $this->vocabulary->id();
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => Vocabulary::THRESHOLD_DISABLED]);

    // Change vocabulary depth to its maximum (9).
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_depth]" => Vocabulary::DEPTH_MAX]);

    // Assert that all tags are listed in the sitemap.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->pageTextContains($term->label());
    }

    // Change vocabulary depth to 0.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_depth]" => Vocabulary::DEPTH_DISABLED]);

    // Assert that no tags are listed in the sitemap.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->pageTextNotContains($term->label());
    }

    // Change vocabulary depth to 1.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_depth]" => 1]);

    // Assert that only tag 1 is listed in the sitemap.
    $this->drupalGet('/sitemap');
    $this->assertSession()->pageTextContains($this->terms[0]->label());
    $this->assertSession()->pageTextNotContains($this->terms[1]->label());
    $this->assertSession()->pageTextNotContains($this->terms[2]->label());

    // Change vocabulary depth to 2.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_depth]" => 2]);

    // Assert that tag 1 and tag 2 are listed in the sitemap.
    $this->drupalGet('/sitemap');
    $this->assertSession()->pageTextContains($this->terms[0]->label());
    $this->assertSession()->pageTextContains($this->terms[1]->label());
    $this->assertSession()->pageTextNotContains($this->terms[2]->label());

    // Change vocabulary depth to 3.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_depth]" => 3]);

    // Assert that all tags are listed in the sitemap.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->pageTextContains($term->label());
    }

    // Test display when parent term does not meet threshold.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => 1]);
    $childTerms = $this->terms;
    array_shift($childTerms);
    $this->createNodeWithTerms($childTerms);
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->pageTextContains($term->label());
    }

    // @todo Check for empty <li>s as well.
    // Test show_count when parent term does not meet threshold.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][show_count]" => TRUE]);
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary .count:contains('(1)')");
    $this->assertEquals(\count($elements), 2, 'Node counts included.');
  }

  /**
   * Tests the term link settings.
   */
  public function testTermLinks() {
    $this->terms = $this->createTerms($this->vocabulary);
    $this->linkSettingsTest();
  }

  /**
   * Tests the nested term link settings.
   */
  public function testNestedTermLinks() {
    // Create terms.
    $this->terms = $this->createNestedTerms($this->vocabulary);
    $this->linkSettingsTest();

    // Test link when parent term does not meet threshold.
    $vid = $this->vocabulary->id();
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => 2]);
    $childTerms = $this->terms;
    array_shift($childTerms);
    $this->createNodeWithTerms($childTerms);
    $this->drupalGet('/sitemap');
    $this->assertSession()->linkNotExists($this->terms[0]->label());
    foreach ($childTerms as $term) {
      $this->assertSession()->linkExists($term->label());
    }

    // Test 'always display links'.
    $vid = $this->vocabulary->id();
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][always_link]" => TRUE]);
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkExists($term->label());
    }
  }

  /* @todo Tests customized term links. */
  /* Public function testTermCustomLinks() { */
  /* }. */

  /**
   * Helper function for testing link settings.
   */
  protected function linkSettingsTest() {
    // Confirm that terms without content are not linked by default.
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkNotExists($term->label());
    }

    // Test 'always display links'.
    $vid = $this->vocabulary->id();
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][always_link]" => TRUE]);
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkExists($term->label());
    }

    // Test that terms with content are linked...
    $this->createNodeWithTerms($this->terms);
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkExists($term->label());
    }

    // ... even when always_link is disabled.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][always_link]" => FALSE]);
    $this->drupalGet('/sitemap');
    foreach ($this->terms as $term) {
      $this->assertSession()->linkExists($term->label());
    }

  }

}
