<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\Url;

/**
 * Test that other modules can define their own Sitemap plugins.
 *
 * @group sitemap
 */
class SitemapPluginApiTest extends SitemapTestBase {

  /**
   * The names of the plugins we're testing.
   */
  protected const PLUGIN_NAME_SIMPLE = 'plugins[sitemap_custom_plugin_test_simple]';
  protected const PLUGIN_NAME_DERIVATIVE_1 = 'plugins[sitemap_custom_plugin_test_derivative:first]';
  protected const PLUGIN_NAME_DERIVATIVE_2 = 'plugins[sitemap_custom_plugin_test_derivative:second]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitemap_custom_plugin_test'];

  /**
   * Test a custom module can define plugins; they appear on the settings page.
   */
  public function testCustomModulePluginsOnSettingsForm(): void {
    // Load the settings page.
    $this->drupalLogin($this->userAdmin);
    $this->drupalGet(Url::fromRoute('sitemap.settings'));

    // Verify that the sitemap_custom_plugin_test_simple plugin was detected in
    // the "Enabled plugins" section.
    $this->assertSession()->checkboxChecked(self::PLUGIN_NAME_SIMPLE . '[enabled]');

    // Verify that the sitemap_custom_plugin_test_derivative plugin was detected
    // in the "Enabled plugins" section.
    $this->assertSession()->checkboxChecked(self::PLUGIN_NAME_DERIVATIVE_1 . '[enabled]');
    $this->assertSession()->checkboxChecked(self::PLUGIN_NAME_DERIVATIVE_2 . '[enabled]');

    // Verify that the sitemap_custom_plugin_test_simple plugin settings form is
    // rendered in the "Plugin settings" section.
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_SIMPLE . '[settings][title]', 'Test simple plugin');
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_SIMPLE . '[settings][fizz]', 'buzz');

    // Verify that the sitemap_custom_plugin_test_derivative plugin settings
    // form is rendered in the "Plugin settings" section.
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_DERIVATIVE_1 . '[settings][title]', 'First derivative sitemap plugin');
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_DERIVATIVE_1 . '[settings][bizz]', 'Lorem ipsum dolor');
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_DERIVATIVE_2 . '[settings][title]', 'Second derivative sitemap plugin');
    $this->assertSession()->fieldValueEquals(self::PLUGIN_NAME_DERIVATIVE_2 . '[settings][bizz]', 'Sit amet adipiscing');
  }

  /**
   * Test a custom module can define plugins, and they appear on the sitemap.
   */
  public function testCustomModulePluginsAppearOnSitemap(): void {
    $this->drupalLogin($this->userAdmin);
    $this->saveSitemapForm([
      self::PLUGIN_NAME_SIMPLE . '[enabled]' => TRUE,
      self::PLUGIN_NAME_DERIVATIVE_1 . '[enabled]' => TRUE,
      self::PLUGIN_NAME_DERIVATIVE_2 . '[enabled]' => TRUE,
    ]);

    // Load the sitemap.
    $this->drupalLogin($this->userView);
    $this->drupalGet(Url::fromRoute('sitemap.page'));

    // Verify that the sitemap_custom_plugin_test_simple plugin section appears.
    $this->assertSession()->elementTextEquals('css', '.sitemap-item--sitemap-custom-plugin-test-simple > h2', 'Test simple plugin');
    $this->assertSession()->elementTextEquals('xpath', '//*[contains(@class, "sitemap-item--sitemap-custom-plugin-test-simple")]/div/a[contains(@href, "/admin/content")]', 'buzz');

    // Verify that the sitemap_custom_plugin_test_derivative plugin sections
    // appear.
    $this->assertSession()->elementTextEquals('css', '.sitemap-item--sitemap-custom-plugin-test-derivative-first > h2', 'First derivative sitemap plugin');
    $this->assertSession()->elementTextEquals('xpath', '//*[contains(@class, "sitemap-item--sitemap-custom-plugin-test-derivative-first")]/div/a[contains(@href, "/admin/reports")]', 'Lorem ipsum dolor');
    $this->assertSession()->elementTextEquals('css', '.sitemap-item--sitemap-custom-plugin-test-derivative-second > h2', 'Second derivative sitemap plugin');
    $this->assertSession()->elementTextEquals('xpath', '//*[contains(@class, "sitemap-item--sitemap-custom-plugin-test-derivative-second")]/div/a[contains(@href, "/admin/reports")]', 'Sit amet adipiscing');
  }

}
