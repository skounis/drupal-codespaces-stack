<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Tests\sitemap\Traits\SitemapTestTrait;

/**
 * Tests the display of taxonomies based on sitemap settings.
 *
 * @group sitemap
 */
class SitemapTaxonomyTest extends SitemapTaxonomyTestBase {

  use SitemapTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['sitemap', 'node', 'taxonomy'];

  /**
   * Test that disabled sub-terms are skipped in a multi-level taxonomy.
   */
  public function testShowDisabledSubTerms(): void {
    $vid = $this->vocabulary->id();
    // The level-1 word 'the' should appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'the',
      'status' => TRUE,
    ]);
    // The level-1 word 'quick' should NOT appear when show_disabled is FALSE.
    $term1 = $this->createTerm($this->vocabulary, [
      'name' => 'quick',
      'status' => FALSE,
    ]);
    // The level-2 word 'brown' should NOT appear when show_disabled is FALSE,
    // because its parent is 'quick'.
    $this->createTerm($this->vocabulary, [
      'name' => 'brown',
      'status' => TRUE,
      'parent' => $term1->id(),
    ]);
    // The level-1 word 'fox' should appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'fox',
      'status' => TRUE,
    ]);
    // The level-1 word 'jumps' should appear when show_disabled is FALSE.
    $term2 = $this->createTerm($this->vocabulary, [
      'name' => 'jumps',
      'status' => TRUE,
    ]);
    // The level-2 word 'over' should appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'over',
      'status' => TRUE,
      'parent' => $term2->id(),
    ]);
    // The level-2 word 'lazy' should NOT appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'lazy',
      'status' => FALSE,
      'parent' => $term2->id(),
    ]);
    // The level-2 word 'dog' should appear when show_disabled is FALSE.
    $term3 = $this->createTerm($this->vocabulary, [
      'name' => 'dog',
      'status' => TRUE,
      'parent' => $term2->id(),
    ]);
    // The level-3 word 'sphinx' should appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'sphinx',
      'status' => TRUE,
      'parent' => $term3->id(),
    ]);
    // The level-3 word 'waltz' should NOT appear when show_disabled is FALSE.
    $term4 = $this->createTerm($this->vocabulary, [
      'name' => 'waltz',
      'status' => FALSE,
      'parent' => $term3->id(),
    ]);
    // The level-4 word 'black' should NOT appear when show_disabled is FALSE,
    // because its parent is 'waltz'.
    $this->createTerm($this->vocabulary, [
      'name' => 'black',
      'status' => TRUE,
      'parent' => $term4->id(),
    ]);
    // The level-4 word 'quartz' should NOT appear when show_disabled is FALSE,
    // because its parent is 'waltz', and it is unpublished.
    $this->createTerm($this->vocabulary, [
      'name' => 'quartz',
      'status' => FALSE,
      'parent' => $term4->id(),
    ]);
    // The level-3 word 'judge' should appear when show_disabled is FALSE.
    $term5 = $this->createTerm($this->vocabulary, [
      'name' => 'judge',
      'status' => TRUE,
      'parent' => $term3->id(),
    ]);
    // The level-4 word 'my' should appear when show_disabled is FALSE.
    $term6 = $this->createTerm($this->vocabulary, [
      'name' => 'my',
      'status' => TRUE,
      'parent' => $term5,
    ]);
    // The level-5 word 'vow' should NOT appear when show_disabled is FALSE.
    $this->createTerm($this->vocabulary, [
      'name' => 'vow',
      'status' => FALSE,
      'parent' => $term6,
    ]);

    // Check that we can see the published terms on the sitemap but not the
    // unpublished one.
    $this->drupalLogin($this->drupalCreateUser(['access sitemap']));
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
    $this->assertSession()->pageTextContains('the');
    $this->assertSession()->pageTextNotContains('quick');
    $this->assertSession()->pageTextNotContains('brown');
    $this->assertSession()->pageTextContains('fox');
    $this->assertSession()->pageTextContains('jumps');
    $this->assertSession()->pageTextContains('over');
    $this->assertSession()->pageTextNotContains('lazy');
    $this->assertSession()->pageTextContains('dog');
    $this->assertSession()->pageTextContains('sphinx');
    $this->assertSession()->pageTextNotContains('waltz');
    $this->assertSession()->pageTextNotContains('black');
    $this->assertSession()->pageTextNotContains('quartz');
    $this->assertSession()->pageTextContains('judge');
    $this->assertSession()->pageTextContains('my');
    $this->assertSession()->pageTextNotContains('vow');

    // Make sure "Display unpublished taxonomy terms" control is hidden for
    // users without permission to see it.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/search/sitemap');
    $this->assertSession()->fieldNotExists("plugins[vocabulary:$vid][settings][display_unpublished]");

    // Now, show unpublished taxonomy terms.
    $this->drupalLogin($this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      'administer nodes',
      'create article content',
      'administer taxonomy',
      'show unpublished taxonomy terms on sitemap',
    ]));
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][display_unpublished]" => TRUE]);

    // Check that we can see both published and unpublished terms on the
    // sitemap.
    $this->drupalLogin($this->drupalCreateUser(['access sitemap']));
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
    $this->assertSession()->pageTextContains('the');
    $this->assertSession()->pageTextContains('quick');
    $this->assertSession()->pageTextContains('brown');
    $this->assertSession()->pageTextContains('fox');
    $this->assertSession()->pageTextContains('jumps');
    $this->assertSession()->pageTextContains('over');
    $this->assertSession()->pageTextContains('lazy');
    $this->assertSession()->pageTextContains('dog');
    $this->assertSession()->pageTextContains('sphinx');
    $this->assertSession()->pageTextContains('waltz');
    $this->assertSession()->pageTextContains('black');
    $this->assertSession()->pageTextContains('quartz');
    $this->assertSession()->pageTextContains('judge');
    $this->assertSession()->pageTextContains('my');
    $this->assertSession()->pageTextContains('vow');
  }

  /**
   * Test the "Display unpublished taxonomy terms" control.
   */
  public function testShowDisabledTerms(): void {
    // Create three taxonomy terms, one of which is unpublished.
    $vid = $this->vocabulary->id();
    $this->createTerm($this->vocabulary, [
      'name' => 'lorem',
      'status' => TRUE,
    ]);
    $this->createTerm($this->vocabulary, [
      'name' => 'ipsum',
      'status' => FALSE,
    ]);
    $this->createTerm($this->vocabulary, [
      'name' => 'dolor',
      'status' => TRUE,
    ]);

    // Check that we can see the published terms on the sitemap but not the
    // unpublished one.
    $this->drupalLogin($this->drupalCreateUser(['access sitemap']));
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
    $this->assertSession()->pageTextContains('lorem');
    $this->assertSession()->pageTextNotContains('ipsum');
    $this->assertSession()->pageTextContains('dolor');

    // Make sure "Display unpublished taxonomy terms" control is hidden for
    // users without permission to see it.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/search/sitemap');
    $this->assertSession()->fieldNotExists("plugins[vocabulary:$vid][settings][display_unpublished]");

    // Now, show unpublished taxonomy terms.
    $this->drupalLogin($this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      'administer nodes',
      'create article content',
      'administer taxonomy',
      'show unpublished taxonomy terms on sitemap',
    ]));
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][display_unpublished]" => TRUE]);

    // Check that we can see both published and unpublished terms on the
    // sitemap.
    $this->drupalLogin($this->drupalCreateUser(['access sitemap']));
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
    $this->assertSession()->pageTextContains('lorem');
    $this->assertSession()->pageTextContains('ipsum');
    $this->assertSession()->pageTextContains('dolor');
  }

  /**
   * Tests vocabulary title.
   */
  public function testVocabularyTitle() {
    // The vocabulary is already configured to display in parent ::setUp().
    $vocab = $this->vocabulary;
    $vid = $vocab->id();
    $this->createTerms($vocab);

    $this->titleTest($vocab->label(), 'vocabulary', $vid, TRUE);
  }

  /**
   * Tests vocabulary description.
   */
  public function testVocabularyDescription() {
    // The vocabulary is already configured to display in ::setUp().
    $vid = $this->vocabulary->id();
    $this->createTerms($this->vocabulary);

    // Assert that vocabulary description is not included by default.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
    $this->assertSession()->pageTextNotContains($this->vocabulary->getDescription());

    // Display the description.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][show_description]" => TRUE]);
    $this->assertSession()->pageTextContains($this->vocabulary->getDescription());

    // Create taxonomy terms.
    $this->createTerms($this->vocabulary);

    // Set to show all taxonomy terms, even if they are not assigned to any
    // nodes.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][term_count_threshold]" => -1]);

    // Assert that the vocabulary description is included in the sitemap when
    // terms are displayed.
    $this->drupalGet('/sitemap');
    $this->assertSession()->pageTextContains($this->vocabulary->getDescription());

    // Configure sitemap not to show vocabulary descriptions.
    $this->saveSitemapForm(["plugins[vocabulary:$vid][settings][show_description]" => FALSE]);

    // Assert that vocabulary description is not included in the sitemap.
    $this->drupalGet('/sitemap');
    $this->assertSession()->pageTextNotContains($this->vocabulary->getDescription());
  }

  /**
   * Test seamless functionality when created and deleting vocabularies.
   */
  public function testVocabularyCrud() {
    $this->createTerms($this->vocabulary);
    // Create an additional vocabulary.
    $vocabularyToDelete = $this->createVocabulary();
    $this->createTerms($vocabularyToDelete);

    // Configure the sitemap to display both vocabularies.
    $vid = $this->vocabulary->id();
    $vid_to_delete = $vocabularyToDelete->id();
    $edit = [
      "plugins[vocabulary:$vid][enabled]" => TRUE,
      "plugins[vocabulary:$vid_to_delete][enabled]" => TRUE,
    ];
    $this->saveSitemapForm($edit);

    // Ensure that both vocabularies are displayed.
    $this->drupalGet('/sitemap');

    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 2, '2 vocabularies are included');

    $elements = $this->cssSelect(".sitemap-item--vocabulary-$vid");
    $this->assertEquals(\count($elements), 1, "Vocabulary $vid is included.");
    $elements = $this->cssSelect(".sitemap-item--vocabulary-$vid_to_delete");
    $this->assertEquals(\count($elements), 1, "Vocabulary $vid_to_delete is included.");

    // Delete the vocabulary.
    $vocabularyToDelete->delete();
    // @todo We shouldn't have to do this if vocab cache tags are in place...
    drupal_flush_all_caches();

    // Visit /sitemap.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, '1 vocabulary is included');

    $elements = $this->cssSelect(".sitemap-item--vocabulary-$vid");
    $this->assertEquals(\count($elements), 1, "Vocabulary $vid is included.");
    $elements = $this->cssSelect(".sitemap-item--vocabulary-$vid_to_delete");
    $this->assertEquals(\count($elements), 0, "Vocabulary $vid_to_delete is included.");

    // Visit the sitemap configuration page to ensure no errors there.
    $this->drupalGet('/admin/config/search/sitemap');
  }

  /**
   * Tests if the sitemap loads correctly after the taxonomy view gets disabled.
   */
  public function testWithDisabledTaxonomyView() {
    // Enable the Views module.
    $this->container->get('module_installer')
      ->install(['views']);

    // Create taxonomy terms.
    $this->createTerms($this->vocabulary);

    // Ensure that the sitemap gets loaded correctly.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');

    // Now disable the taxonomy view.
    $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load('taxonomy_term')
      ->setStatus(FALSE)
      ->save();

    // Flush cache to regenerate the sitemap.
    drupal_flush_all_caches();

    // And visit the sitemap again.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--vocabulary");
    $this->assertEquals(\count($elements), 1, 'Vocabulary found.');
  }

}
