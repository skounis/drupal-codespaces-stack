<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Functional;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager_test_validation\StagedDatabaseUpdateValidator;

/**
 * @covers \Drupal\automatic_updates_extensions\Form\UpdaterForm
 * @group automatic_updates_extensions
 * @internal
 */
final class SuccessfulUpdateTest extends UpdaterFormTestBase {

  /**
   * Data provider for testSuccessfulUpdate().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerSuccessfulUpdate(): array {
    return [
      'maintenance mode on, semver module' => [
        TRUE, 'semver_test', 'drupal/semver_test_package_name', 'Semver Test', '8.1.0', '8.1.1',
      ],
      'maintenance mode off, legacy module' => [
        FALSE, 'aaa_update_test', 'drupal/aaa_update_test', 'AAA Update test', '8.x-2.0', '8.x-2.1',
      ],
      'maintenance mode off, legacy theme' => [
        FALSE, 'automatic_updates_extensions_test_theme', 'drupal/automatic_updates_extensions_test_theme', 'Automatic Updates Extensions Test Theme', '8.x-2.0', '8.x-2.1',
      ],
    ];
  }

  /**
   * Tests an update that has no errors or special conditions.
   *
   * @param bool $maintenance_mode_on
   *   Whether maintenance should be on at the beginning of the update.
   * @param string $project_name
   *   The project name.
   * @param string $package_name
   *   The package name.
   * @param string $project_title
   *   The project title.
   * @param string $installed_version
   *   The installed version.
   * @param string $target_version
   *   The target version.
   *
   * @dataProvider providerSuccessfulUpdate
   */
  public function testSuccessfulUpdate(bool $maintenance_mode_on, string $project_name, string $package_name, string $project_title, string $installed_version, string $target_version): void {
    $this->container->get('theme_installer')->install(['automatic_updates_theme_with_updates']);
    // By default, the Update module only checks for updates of installed
    // modules and themes. The two modules we're testing here (semver_test and
    // aaa_update_test) are already installed by static::$modules.
    $this->container->get('theme_installer')->install(['automatic_updates_extensions_test_theme']);
    $this->setReleaseMetadata(__DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml');
    $path_to_fixtures_folder = $project_name === 'aaa_update_test' ? '/../../../../package_manager/tests' : '/../..';
    $this->setReleaseMetadata(__DIR__ . $path_to_fixtures_folder . '/fixtures/release-history/' . $project_name . '.1.1.xml');
    $this->setProjectInstalledVersion([$project_name => $installed_version]);
    $this->getStageFixtureManipulator()->setVersion($package_name, LegacyVersionUtility::convertToSemanticVersion($target_version));
    $this->checkForUpdates();
    $state = $this->container->get('state');
    $state->set('system.maintenance_mode', $maintenance_mode_on);
    StagedDatabaseUpdateValidator::setExtensionsWithUpdates([
      'system',
      'automatic_updates_theme_with_updates',
    ]);

    $page = $this->getSession()->getPage();
    // Navigate to the automatic updates form.
    $this->drupalGet('/admin/reports/updates');
    $this->clickLink('Update Extensions');
    $this->assertTableShowsUpdates(
      $project_title,
      $installed_version,
      $target_version
    );
    $assert_session = $this->assertSession();
    $this->assertUpdatesCount(1);

    // Submit without selecting a project.
    $page->pressButton('Update');
    $assert_session->pageTextContains('Select one or more projects.');

    // Submit with a project selected.
    $page->checkField('projects[' . $project_name . ']');
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    // Confirm that the site was put into maintenance mode if needed.
    $this->assertSame($state->get('system.maintenance_mode'), $maintenance_mode_on);

    $assert_session->pageTextNotContains('The following dependencies will also be updated:');
    // Ensure that a list of pending database updates is visible, along with a
    // short explanation, in the warning messages.
    $warning_messages = $assert_session->elementExists('xpath', '//div[@data-drupal-messages]//div[@aria-label="Warning message"]');
    $this->assertStringContainsString('Database updates have been detected in the following extensions.<ul><li>System</li><li>Automatic Updates Theme With Updates</li></ul>', $warning_messages->getHtml());

    $this->acceptWarningAndUpdate();
    $assert_session->addressEquals('/admin/reports/updates');
    // Confirm that the site was in maintenance before the update was applied.
    // @see \Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber::handleEvent()
    $this->assertTrue($state->get(PreApplyEvent::class . '.system.maintenance_mode'));
    $assert_session->pageTextContainsOnce('Update complete!');
    // Confirm the site was returned to the original maintenance mode state.
    $this->assertSame($state->get('system.maintenance_mode'), $maintenance_mode_on);
    // Confirm that the apply and post-apply operations happened in
    // separate requests.
    // @see \Drupal\automatic_updates_test\EventSubscriber\RequestTimeRecorder
    $pre_apply_time = $state->get('Drupal\package_manager\Event\PreApplyEvent time');
    $post_apply_time = $state->get('Drupal\package_manager\Event\PostApplyEvent time');
    $this->assertNotEmpty($pre_apply_time);
    $this->assertNotEmpty($post_apply_time);
    $this->assertNotSame($pre_apply_time, $post_apply_time);
  }

}
