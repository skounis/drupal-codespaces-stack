<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class PreUpdateTest extends UpdaterFormTestBase {

  /**
   * Tests status checks are displayed when there is no update available.
   */
  public function testStatusCheckFailureWhenNoUpdateExists() {
    $assert_session = $this->assertSession();
    $this->mockActiveCoreVersion('9.8.1');
    $message = t("You've not experienced Shakespeare until you have read him in the original Klingon.");
    $result = ValidationResult::createError([$message]);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session->pageTextContains('No update available');
    $assert_session->pageTextContains($message->render());
  }

  /**
   * Checks RC releases of the next minor are available on the form.
   */
  public function testNextMinorRc(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.0-rc1.xml');
    $this->mockActiveCoreVersion('9.7.0');
    $this->config('automatic_updates.settings')
      ->set('allow_core_minor_updates', TRUE)
      ->save();
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session = $this->assertSession();
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-recommended', '9.8.0-rc1', FALSE, 'Latest version of Drupal 9.8 (next minor):');
    $assert_session->pageTextContainsOnce('Currently installed: 9.7.0 (Up to date)');
  }

  /**
   * Checks Beta releases of the next minor are not available on the form.
   */
  public function testNextMinorBeta(): void {
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.0-beta1.xml');
    $this->mockActiveCoreVersion('9.7.0');
    $this->config('automatic_updates.settings')
      ->set('allow_core_minor_updates', TRUE)
      ->save();
    $this->checkForUpdates();
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session = $this->assertSession();
    $assert_session->statusMessageContains('No update available');
  }

}
