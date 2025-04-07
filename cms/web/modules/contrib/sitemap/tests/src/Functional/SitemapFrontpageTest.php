<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Tests\sitemap\Traits\SitemapTestTrait;

/**
 * Tests the display of RSS links based on sitemap settings.
 *
 * @group sitemap
 */
class SitemapFrontpageTest extends SitemapBrowserTestBase {

  use SitemapTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['sitemap'];

  /**
   * The user account to use when running the test.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create user, then login.
    $this->user = $this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
    ]);
    $this->drupalLogin($this->user);
    $this->saveSitemapForm(["plugins[frontpage][enabled]" => TRUE]);
  }

  /**
   * Tests the custom title setting.
   */
  public function testTitle() {
    $this->titleTest('Front page', 'frontpage', '', TRUE);
  }

  /**
   * Tests RSS feed for front page.
   */
  public function testRssFeed() {
    // Assert default RSS feed for front page.
    $this->drupalGet('/sitemap');
    $this->assertSession()->linkByHrefExists('/rss.xml');
    $elements = $this->cssSelect(".sitemap-plugin--frontpage img");
    $this->assertEquals(\count($elements), 1, 'RSS icon is included.');

    // Make sure "Feed URL" control is hidden for users without permission to
    // see it.
    $this->drupalGet('admin/config/search/sitemap');
    $this->assertSession()->fieldNotExists('plugins[frontpage][settings][rss]');

    // Change RSS feed for front page.
    $this->drupalLogin($this->drupalCreateUser([
      'administer sitemap',
      'set front page rss link on sitemap',
      'access sitemap',
    ]));
    $href = \mb_strtolower($this->randomMachineName());
    $this->saveSitemapForm(['plugins[frontpage][settings][rss]' => '/' . $href]);

    // Assert that RSS feed for front page has been changed.
    $this->drupalGet('/sitemap');
    $this->assertSession()->linkByHrefExists('/' . $href);

    // Assert that the RSS feed can be removed entirely.
    $this->saveSitemapForm(['plugins[frontpage][settings][rss]' => '']);
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--frontpage img");
    $this->assertEquals(\count($elements), 0, 'RSS icon is not included.');

  }

}
