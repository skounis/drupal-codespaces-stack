<?php

namespace Drupal\Tests\sitemap\Traits;

/**
 * Provides a title test.
 */
trait SitemapTestTrait {

  /**
   * Common test for the plugin title field.
   *
   * @param string $title
   *   The title to be tested.
   * @param string $plugin_id
   *   The plugin id.
   * @param string $derivative_id
   *   The derivative id.
   * @param bool $clear_cache
   *   Some elements do not yet have proper cache tags configured (looking at
   *   you, vocabulary), so cache must be flushed when plugin configuration
   *   changes.
   *
   * @throws \Exception
   */
  public function titleTest($title, $plugin_id, $derivative_id = '', $clear_cache = FALSE) {
    $field = $derivative_id ? $plugin_id . ':' . $derivative_id : $plugin_id;

    // Assert that title is found.
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $this->drupalGet('/sitemap');
    $assert->elementExists('css', ".sitemap-plugin--$plugin_id");
    $assert->elementTextContains('css', ".sitemap-plugin--$plugin_id h2", $title);

    // Remove the title.
    $this->saveSitemapForm(["plugins[$field][settings][title]" => '']);
    if ($clear_cache) {
      drupal_flush_all_caches();
    }

    // Check that a title does not appear on sitemap.
    $this->drupalGet('/sitemap');
    $assert->elementNotExists('css', ".sitemap-plugin--$plugin_id h2");

    // Set a custom title for the main menu display.
    $custom_title = $this->randomString();
    $this->saveSitemapForm(["plugins[$field][settings][title]" => $custom_title]);
    if ($clear_cache) {
      drupal_flush_all_caches();
    }

    // Check that the custom title appears on the sitemap.
    $this->drupalGet('/sitemap');
    $assert->elementExists('css', ".sitemap-plugin--$plugin_id");
    $assert->elementTextContains('css', ".sitemap-plugin--$plugin_id h2", $custom_title);
  }

}
