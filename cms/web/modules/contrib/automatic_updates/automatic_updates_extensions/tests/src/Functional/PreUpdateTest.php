<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Functional;

use Drupal\fixture_manipulator\ActiveFixtureManipulator;

/**
 * @covers \Drupal\automatic_updates_extensions\Form\UpdaterForm
 * @group automatic_updates_extensions
 * @internal
 */
final class PreUpdateTest extends UpdaterFormTestBase {

  /**
   * Tests the form when modules requiring an update not installed via composer.
   */
  public function testNonComposerProjects(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../../../package_manager/tests/fixtures/release-history/aaa_update_test.1.1.xml');
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $this->config('update.settings')
      ->set('fetch.url', $this->baseUrl . '/test-release-history')
      ->save();
    $this->setProjectInstalledVersion(
      [
        'aaa_update_test' => '8.x-2.0',
        'semver_test' => '8.1.0',
      ]
    );
    // One module not installed through composer.
    (new ActiveFixtureManipulator())
      ->removePackage('drupal/aaa_update_test')
      ->commitChanges();
    $assert = $this->assertSession();
    $user = $this->createUser(
      [
        'administer site configuration',
        'administer software updates',
      ]
    );
    $this->drupalLogin($user);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/automatic-update-extensions');
    $assert->pageTextContains('Other updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);

    // Both of the modules not installed through composer.
    (new ActiveFixtureManipulator())
      ->removePackage('drupal/semver_test_package_name')
      ->commitChanges();
    $this->getSession()->reload();
    $assert->pageTextContains('Updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->assertNoUpdates();
  }

  /**
   * Tests the form when a module requires an update.
   */
  public function testHasUpdate(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $assert = $this->assertSession();
    $user = $this->createUser(['administer site configuration']);
    $this->drupalLogin($user);
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->drupalGet('admin/reports/updates/automatic-update-extensions');
    $assert->pageTextContains('Access Denied');
    $assert->pageTextNotContains('Automatic Updates Form');
    $user = $this->createUser(['administer software updates', 'administer site configuration']);
    $this->drupalLogin($user);
    $this->checkForUpdates();
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->assertUpdatesCount(1);
    $assert->pageTextContains('Automatic Updates Form');
    $assert->buttonExists('Update');
  }

  /**
   * Tests the form when there are no available updates.
   */
  public function testNoUpdate(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $user = $this->createUser([
      'administer site configuration',
      'administer software updates',
    ]);
    $this->drupalLogin($user);
    $this->setProjectInstalledVersion(['semver_test' => '8.1.1']);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/updates/automatic-update-extensions');
    $this->assertNoUpdates();
  }

}
