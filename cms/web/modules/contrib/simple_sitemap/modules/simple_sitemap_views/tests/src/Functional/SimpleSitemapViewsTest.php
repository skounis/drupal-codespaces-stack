<?php

namespace Drupal\Tests\simple_sitemap_views\Functional;

use Drupal\simple_sitemap\Entity\SimpleSitemapType;
use Drupal\simple_sitemap\Queue\QueueWorker;

/**
 * Tests Simple XML Sitemap (Views) functional integration.
 *
 * @group simple_sitemap_views
 */
class SimpleSitemapViewsTest extends SimpleSitemapViewsTestBase {

  /**
   * Tests status of sitemap support for views.
   */
  public function testSitemapSupportForViews() {
    // Views support must be enabled after module installation.
    $this->assertTrue($this->sitemapViews->isEnabled());

    $this->sitemapViews->disable();
    $this->assertFalse($this->sitemapViews->isEnabled());

    $this->sitemapViews->enable();
    $this->assertTrue($this->sitemapViews->isEnabled());
  }

  /**
   * Tests indexable views.
   */
  public function testIndexableViews() {
    // Ensure that at least one indexable view exists.
    $indexable_views = $this->sitemapViews->getIndexableViews();
    $this->assertNotEmpty($indexable_views);

    $test_view_exists = FALSE;
    foreach ($indexable_views as $view) {
      if ($view->id() == $this->testView->id() && $view->current_display == $this->testView->current_display) {
        $test_view_exists = TRUE;
        break;
      }
    }
    // The test view should be in the list.
    $this->assertTrue($test_view_exists);

    // Check the indexing status of the arguments.
    $indexable_arguments = $this->sitemapViews->getIndexableArguments($this->testView, $this->sitemapVariant);
    $this->assertContains('type', $indexable_arguments);
    $this->assertContains('title', $indexable_arguments);
    $this->assertNotContains('nid', $indexable_arguments);

    // Check the indexing status of the required arguments.
    $indexable_arguments = $this->sitemapViews->getIndexableArguments($this->testView2, $this->sitemapVariant);
    $this->assertContains('type', $indexable_arguments);
    $this->assertContains('%', $indexable_arguments);
  }

  /**
   * Tests the process of adding arguments to the index.
   */
  public function testAddArgumentsToIndex() {
    // Arguments with the wrong value should not be indexed.
    $this->sitemapViews->addArgumentsToIndex($this->testView, ['page2']);
    $this->assertIndexSize(0);

    // Non-indexable arguments should not be indexed.
    $args = ['page', $this->node->getTitle(), $this->node->id()];
    $this->sitemapViews->addArgumentsToIndex($this->testView, $args);
    $this->assertIndexSize(0);

    // The argument set should not be indexed more than once.
    for ($i = 0; $i < 2; $i++) {
      $this->sitemapViews->addArgumentsToIndex($this->testView, ['page']);
      $this->assertIndexSize(1);
    }

    // A new set of arguments must be indexed.
    $args = ['page', $this->node->getTitle()];
    $this->sitemapViews->addArgumentsToIndex($this->testView, $args);
    $this->assertIndexSize(2);

    // The number of argument sets in the index for one view display should not
    // exceed the maximum number of link variations.
    $args = ['page', $this->node2->getTitle()];
    $this->sitemapViews->addArgumentsToIndex($this->testView, $args);
    $this->assertIndexSize(2);

    // Required arguments must be indexed.
    $this->sitemapViews->addArgumentsToIndex($this->testView2, ['page', 1]);
    $this->assertIndexSize(3);
  }

  /**
   * Tests the process of generating view display URLs.
   */
  public function testViewsUrlGenerator() {
    $this->assertArrayHasKey('views', SimpleSitemapType::load('default_hreflang')->getUrlGenerators());

    $title = $this->node->getTitle();
    $this->sitemapViews->addArgumentsToIndex($this->testView, ['page']);
    $this->sitemapViews->addArgumentsToIndex($this->testView, ['page', $title]);
    $this->sitemapViews->addArgumentsToIndex($this->testView2, ['page', 1]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $url1 = $this->testView->getUrl()->toString();
    $url2 = $this->testView->getUrl(['page', NULL, NULL])->toString();
    $url3 = $this->testView->getUrl(['page', $title, NULL])->toString();
    $url4 = $this->testView2->getUrl()->toString();
    $url5 = $this->testView2->getUrl(['page', 1])->toString();

    // Check that the sitemap contains view display URLs.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains($url1);
    $this->assertSession()->responseContains($url2);
    $this->assertSession()->responseContains($url3);
    $this->assertSession()->responseNotContains($url4);
    $this->assertSession()->responseContains($url5);
  }

  /**
   * Tests the garbage collection process.
   */
  public function testGarbageCollector() {
    // Disable cron generation, since data can be removed
    // from the index during generation.
    $this->generator->saveSetting('cron_generate', FALSE);

    // Record with the wrong set of indexed arguments must be removed.
    $this->addRecordToIndex(
      $this->testView->id(),
      $this->testView->current_display,
      ['type', 'title', 'nid'],
      ['page', $this->node->getTitle(), $this->node->id()]
    );
    $this->cron->run();
    $this->assertIndexSize(0);

    // Record of a non-existent view must be removed.
    $this->addRecordToIndex(
      'simple_sitemap_fake_view',
      $this->testView->current_display,
      ['type', 'title'],
      ['page', $this->node->getTitle()]
    );
    $this->cron->run();
    $this->assertIndexSize(0);

    // Record of a non-existent display must be removed.
    $this->addRecordToIndex(
      $this->testView->id(),
      'simple_sitemap_fake_display',
      ['type', 'title'],
      ['page', $this->node->getTitle()]
    );
    $this->cron->run();
    $this->assertIndexSize(0);

    // The number of records should not exceed the specified limit.
    for ($i = 0; $i < 3; $i++) {
      $this->addRecordToIndex(
        $this->testView->id(),
        $this->testView->current_display,
        ['type', 'title'],
        ['page2', "Node$i"]
      );
    }
    $this->cron->run();
    $this->assertIndexSize(2);

    // Records about pages with empty result must be removed during generation.
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->assertIndexSize(0);
  }

}
