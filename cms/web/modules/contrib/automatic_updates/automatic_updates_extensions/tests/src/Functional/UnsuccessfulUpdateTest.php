<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Functional;

use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;

/**
 * @covers \Drupal\automatic_updates_extensions\Form\UpdaterForm
 * @group automatic_updates_extensions
 * @internal
 */
final class UnsuccessfulUpdateTest extends UpdaterFormTestBase {

  /**
   * Test the form for warning messages.
   */
  public function testWarnings(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $assert = $this->assertSession();
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->checkForUpdates();
    $message = t("Warning! Updating this module may cause an error.");
    $warning = ValidationResult::createWarning([$message]);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    // Navigate to the automatic updates form.
    $this->drupalGet('/admin/reports/updates');
    $this->clickLink('Update Extensions');
    $this->assertTableShowsUpdates('Semver Test', '8.1.0', '8.1.1');
    $this->getStageFixtureManipulator()->setVersion('drupal/semver_test_package_name', '8.1.1');
    $this->assertUpdatesCount(1);
    $this->checkForMetaRefresh();
    $assert->pageTextNotContains(static::$errorsExplanation);
    $assert->elementExists('css', '#edit-projects-semver-test')->check();
    $assert->checkboxChecked('edit-projects-semver-test');
    // No explanation is given for warnings.
    $assert->pageTextNotContains(static::$warningsExplanation);
    $assert->buttonExists('Update');

    // Add warnings from StatusCheckEvent.
    $summary_status_check_event = t('Some summary');
    $messages_status_check_event = [
      t("The only thing we're allowed to do is to"),
      t("believe that we won't regret the choice"),
      t("we made."),
    ];
    $warning_status_check_event = ValidationResult::createWarning($messages_status_check_event, $summary_status_check_event);
    TestSubscriber::setTestResult([$warning_status_check_event], StatusCheckEvent::class);
    $this->getSession()->getPage()->pressButton('Update');
    $this->checkForMetaRefresh();
    $assert->buttonExists('Continue');
    $assert->pageTextContains((string) $summary_status_check_event);
    foreach ($messages_status_check_event as $message) {
      $assert->pageTextContains((string) $message);
    }
  }

  /**
   * Tests the form when an uninstallable module requires an update.
   */
  public function testUninstallableRelease(): void {
    $this->container->get('state')->set('testUninstallableRelease', TRUE);
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $user = $this->createUser(['administer software updates', 'administer site configuration']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/reports/updates/automatic-update-extensions');
    $this->checkForUpdates();
    $this->assertNoUpdates();
  }

}
