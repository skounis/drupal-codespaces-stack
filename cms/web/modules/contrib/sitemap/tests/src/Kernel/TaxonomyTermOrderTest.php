<?php

namespace Drupal\Tests\sitemap\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\sitemap\SitemapManager;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests that terms are ordered according to their weight.
 *
 * @group sitemap
 */
class TaxonomyTermOrderTest extends KernelTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'sitemap',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * The SitemapMap plugin manager.
   *
   * @var \Drupal\sitemap\SitemapManager
   */
  protected SitemapManager $sitemapManager;

  /**
   * A vocabulary entity.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('taxonomy_term');

    $this->sitemapManager = \Drupal::service('plugin.manager.sitemap');
    $this->vocabulary = static::createVocabulary();
  }

  /**
   * @covers \Drupal\sitemap\Plugin\Sitemap\Vocabulary::view
   */
  public function testTermOrder() {
    // Create two top-level terms and two child terms of a single parent.
    // Set the weights so that newer terms have lighter weights than older
    // terms so they should be output earlier in lists.
    $term1 = $this->createTerm($this->vocabulary, [
      'weight' => 0,
    ]);
    $term2 = $this->createTerm($this->vocabulary, [
      'weight' => -1,
    ]);
    $term3 = $this->createTerm($this->vocabulary, [
      'parent' => $term2->id(),
      'weight' => -2,
    ]);
    $term4 = $this->createTerm($this->vocabulary, [
      'parent' => $term2->id(),
      'weight' => -3,
    ]);

    $plugin = $this->sitemapManager->createInstance('vocabulary:' . $this->vocabulary->id(), []);
    $build = $plugin->view();
    $items = $build['#content'][0]['#items'];
    // Check the order of the top-level terms.
    $this->assertSame([2, 1], array_keys($items));
    // Check the order of the child terms.
    $this->assertSame([4, 3], array_keys($items[2]['children']));
  }

}
