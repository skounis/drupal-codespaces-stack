<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests clearing stored project data in various ways.
 *
 * @group project_browser
 */
final class ClearStorageTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private readonly KeyValueStoreInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['project_browser_test_mock'])
      ->save();

    $this->keyValue = \Drupal::service('keyvalue')->get('project_browser:project_browser_test_mock');

    // Warm the project cache and confirm it is populated.
    \Drupal::service(EnabledSourceHandler::class)->getProjects('project_browser_test_mock');
    $this->assertNotEmpty($this->keyValue->getAll());
  }

  /**
   * Tests clearing the cache by calling the method which does it.
   *
   * @covers \Drupal\project_browser\EnabledSourceHandler::clearStorage
   */
  public function testClearCacheDirectly(): void {
    \Drupal::service(EnabledSourceHandler::class)->clearStorage();
    $this->assertEmpty($this->keyValue->getAll());
  }

  /**
   * Tests clearing the cache at the command line with Drush.
   *
   * @param string $command
   *   The name or alias of the Drush command.
   *
   * @testWith ["project-browser:storage-clear"]
   *   ["pb-sc"]
   */
  public function testClearCacheWithDrush(string $command): void {
    $this->drush($command);
    $this->assertEmpty($this->keyValue->getAll());
  }

  /**
   * Tests clearing the cache via the actions form.
   */
  public function testClearCacheViaForm(): void {
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/config/development/project_browser/actions');

    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Clear storage')->press();
    $assert_session->statusMessageContains('Storage cleared.');
    $this->assertEmpty($this->keyValue->getAll());
  }

}
