<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Core\Url;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests routing of source plugins.
 *
 * @group project_browser
 */
final class RoutingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser_test'];

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
   * Tests sources before and after enabling them.
   */
  public function testSources(): void {
    $assert_session = $this->assertSession();

    $url = Url::fromRoute('project_browser.browse', [
      'source' => 'drupal_core',
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(404);

    // Enable another source plugin and ensure that the enabled source handler
    // is aware of it.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock',
        'drupal_core',
      ])
      ->save();

    $enabled_source_ids = array_keys($this->container->get(EnabledSourceHandler::class)->getCurrentSources());
    sort($enabled_source_ids);
    $expected = [
      'drupal_core',
      'project_browser_test_mock',
    ];
    $this->assertSame($expected, $enabled_source_ids);

    foreach ($enabled_source_ids as $plugin_id) {
      $url = Url::fromRoute('project_browser.browse', [
        'source' => $plugin_id,
      ]);
      $this->drupalGet($url);
      $assert_session->statusCodeEquals(200);
    }
  }

}
