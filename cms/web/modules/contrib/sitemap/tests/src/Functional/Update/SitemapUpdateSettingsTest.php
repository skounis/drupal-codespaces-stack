<?php

namespace Drupal\Tests\sitemap\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests updates to Sitemap settings configuration.
 *
 * @group Update
 * @group sitemap
 */
class SitemapUpdateSettingsTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      $this->root . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/sitemap-8200.php',
    ];
  }

  /**
   * @covers \sitemap_update_8201()
   * @covers \sitemap_update_8203()
   * @covers \sitemap_update_8204()
   */
  public function testUpdateHookN(): void {
    $old_settings = \Drupal::config('sitemap.settings');
    foreach ($old_settings->get('plugins') as $config) {
      $this->assertFalse(isset($config['base_plugin']));
    }

    $this->runUpdates();

    // Test sitemap_update_8203().
    $new_settings = \Drupal::config('sitemap.settings');
    $this->assertSame('frontpage', $new_settings->get('plugins.frontpage.base_plugin'));
    $this->assertSame('menu', $new_settings->get('plugins.menu:main.base_plugin'));
    $this->assertSame('vocabulary', $new_settings->get('plugins.vocabulary:tags.base_plugin'));

    // Test sitemap_update_8201() and sitemap_update_8204().
    $this->assertSame('sitemap', $new_settings->get('path'));
    $this->drupalLogin($this->createUser([
      'access sitemap',
      'administer sitemap',
    ]));
    $this->drupalGet('sitemap');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/config/search/sitemap');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('path', 'sitemap');
    $this->drupalLogout();
  }

}
