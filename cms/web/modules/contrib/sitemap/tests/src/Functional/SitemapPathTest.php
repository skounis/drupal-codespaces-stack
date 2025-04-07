<?php

namespace Drupal\Tests\sitemap\Functional;

/**
 * Test the feature which allows us to change the path to the sitemap.
 *
 * @group sitemap
 */
class SitemapPathTest extends SitemapTestBase {

  /**
   * Ensure that we can change the path of the sitemap.
   */
  public function testChangeSitemapPath(): void {
    $exampleSitemapText = $this->getRandomGenerator()->sentences(1);
    $sitemapPath1 = '/sitemap';
    $sitemapPath2 = '/pametis';

    // Set sitemap path to "/sitemap".
    $this->drupalLogin($this->userAdmin);
    $this->saveSitemapForm([
      'path' => $sitemapPath1,
      'page_title' => $exampleSitemapText,
    ]);

    $this->drupalLogin($this->userView);

    // Verify sitemap is at "/sitemap".
    $this->drupalGet($sitemapPath1);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($exampleSitemapText);

    // Verify sitemap is not at "/pametis" (i.e.: "sitemap" backwards).
    $this->drupalGet($sitemapPath2);
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextNotContains($exampleSitemapText);

    // Set sitemap path to "/pametis".
    $this->drupalLogin($this->userAdmin);
    $this->saveSitemapForm([
      'path' => $sitemapPath2,
      'page_title' => $exampleSitemapText,
    ]);

    $this->drupalLogin($this->userView);

    // Verify sitemap is no longer at "/sitemap".
    $this->drupalGet($sitemapPath1);
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextNotContains($exampleSitemapText);

    // Verify sitemap is now at "/pametis".
    $this->drupalGet($sitemapPath2);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($exampleSitemapText);
  }

}
