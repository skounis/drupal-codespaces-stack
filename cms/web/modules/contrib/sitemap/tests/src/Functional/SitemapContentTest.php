<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\filter\Entity\FilterFormat;

/**
 * Test configurable content on the Sitemap page.
 *
 * @group sitemap
 */
class SitemapContentTest extends SitemapTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['sitemap', 'block', 'filter'];

  /**
   * Content editor user.
   *
   * @var array
   */
  public $userEditor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place page title block.
    $this->drupalPlaceBlock('page_title_block');

    // Create filter format.
    $restricted_html_format = FilterFormat::create([
      'format' => 'restricted_html',
      'name' => 'Restricted HTML',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'weight' => -10,
          'settings' => [
            'allowed_html' => '<p> <br /> <strong> <a> <em> <h4>',
          ],
        ],
        'filter_autop' => [
          'status' => TRUE,
          'weight' => 0,
        ],
        'filter_url' => [
          'status' => TRUE,
          'weight' => 0,
        ],
        'filter_htmlcorrector' => [
          'status' => TRUE,
          'weight' => 10,
        ],
      ],
    ]);
    $restricted_html_format->save();

    // Create user then login.
    $this->userEditor = $this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      $restricted_html_format->getPermissionName(),
    ]);
    $this->drupalLogin($this->userEditor);
  }

  /**
   * Tests configurable page title.
   */
  public function testPageTitle() {
    // Assert default page title.
    $this->drupalGet('/sitemap');
    $this->assertSession()->titleEquals('Sitemap | Drupal');

    // Change page title.
    $new_title = $this->randomMachineName();
    $edit = [
      'page_title' => $new_title,
    ];
    $this->saveSitemapForm($edit);
    drupal_flush_all_caches();

    // Assert that page title is changed.
    $this->drupalGet('/sitemap');
    $this->assertSession()->titleEquals("$new_title | Drupal");
  }

  /**
   * Tests sitemap message.
   */
  public function testSitemapMessage() {
    // Assert that sitemap message is not included in the sitemap by default.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect('.sitemap-message');
    $this->assertEquals(\count($elements), 0, 'Sitemap message is not included.');

    // Change sitemap message.
    $new_message = $this->randomMachineName(16);
    $edit = [
      'message[value]' => $new_message,
    ];
    $this->saveSitemapForm($edit);
    drupal_flush_all_caches();

    // Assert sitemap message is included in the sitemap.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-message:contains('" . $new_message . "')");
    $this->assertEquals(\count($elements), 1, 'Sitemap message is included.');
  }

}
