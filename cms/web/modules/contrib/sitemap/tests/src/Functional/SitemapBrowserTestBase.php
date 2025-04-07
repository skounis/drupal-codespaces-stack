<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test behaviors when saving the sitemap form.
 *
 * @group sitemap
 */
abstract class SitemapBrowserTestBase extends BrowserTestBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Save the sitemap form with the provided configuration.
   *
   * @param array $edit
   *   The array with the form fields.
   */
  protected function saveSitemapForm(array $edit = []) {
    $this->drupalGet('admin/config/search/sitemap');
    $this->submitForm($edit, 'Save configuration');
  }

}
