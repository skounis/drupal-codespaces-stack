<?php

namespace Drupal\Tests\yoast_seo\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Real-Time SEO configuration page.
 *
 * @group yoast_seo_ui
 */
class ConfigurationPageTest extends BrowserTestBase {

  /**
   * The theme to use for this test.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
    'yoast_seo',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    // Create an article content type that we will use for testing.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that a user requires the 'administer yoast seo' permission.
   *
   * The permission is required to access the configuration page.
   */
  public function testAdministerPermissionRequirement() {
    $unauthorized = $this->drupalCreateUser();
    $authorized = $this->drupalCreateUser(['administer yoast seo']);

    // Test that a user without the permission is denied.
    $this->drupalLogin($unauthorized);

    $this->drupalGet('/admin/config/yoast_seo');
    $this->assertSession()->statusCodeEquals(403);

    // Test that a user with the permission can see the page.
    $this->drupalLogin($authorized);

    $this->drupalGet('/admin/config/yoast_seo');
    $this->assertSession()->statusCodeEquals(200);
  }

}
