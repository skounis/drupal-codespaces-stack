<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Project Browser Menu tabs placement.
 *
 * @group project_browser
 */
final class ProjectBrowserMenuTabsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'project_browser',
    'project_browser_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('project_browser.admin_settings')->set('enabled_sources', ['project_browser_test_mock'])->save(TRUE);
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
    ]));
  }

  /**
   * Tests browse menu link.
   */
  public function testBrowseMenuPosition(): void {
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    // Assert that the second tab in the nav bar is the Browse tab.
    // @todo Use elementTextEquals() once support for Drupal <10.3 is dropped.
    // @see https://www.drupal.org/project/drupal/issues/3424746
    $this->assertSession()->elementTextContains('css', 'li:nth-child(2)', 'Browse');
  }

}
