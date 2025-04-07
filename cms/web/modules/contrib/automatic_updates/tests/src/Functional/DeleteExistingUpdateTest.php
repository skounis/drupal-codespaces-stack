<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates_test\Datetime\TestTime;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\system\SystemManager;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class DeleteExistingUpdateTest extends UpdaterFormTestBase {

  /**
   * Tests deleting an existing update.
   */
  public function testDeleteExistingUpdate(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $conflict_message = 'Cannot begin an update because another Composer operation is currently in progress.';
    $cancelled_message = 'The update was successfully cancelled.';

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();

    $this->drupalGet('/admin/modules/update');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);

    // Confirm we are on the confirmation page.
    $this->assertUpdateReady('9.8.1');
    $assert_session->buttonExists('Continue');

    // If we try to return to the start page, we should be redirected back to
    // the confirmation page.
    $this->drupalGet('/admin/modules/update');
    $this->assertUpdateReady('9.8.1');

    // Delete the existing update.
    $page->pressButton('Cancel update');
    $assert_session->addressEquals('/admin/reports/updates/update');
    $assert_session->pageTextContains($cancelled_message);
    $assert_session->pageTextNotContains($conflict_message);

    // Ensure we can start another update after deleting the existing one.
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();

    // Confirm we are on the confirmation page.
    $this->assertUpdateReady('9.8.1');
    $this->assertUpdateStagedTimes(2);
    $assert_session->buttonExists('Continue');

    // Log in as another administrative user and ensure that we cannot begin an
    // update because the previous session already started one.
    $account = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session->pageTextContains($conflict_message);
    $this->assertNoUpdateButtons();
    // We should be able to delete the previous update, then start a new one.
    $page->pressButton('Delete existing update');
    $assert_session->pageTextContains('Staged update deleted');
    $assert_session->pageTextNotContains($conflict_message);
    // Before pressing the button, create a new stage fixture manipulator.
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.1');

    // Stop execution during pre-apply. This should make Package Manager think
    // the staged changes are being applied and raise an error if we try to
    // cancel the update.
    TestSubscriber1::setExit(PreApplyEvent::class);
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $page->clickLink('the error page');
    $page->pressButton('Cancel update');
    // The exception should have been caught and displayed in the messages area.
    $assert_session->statusCodeEquals(200);
    $destroy_error = 'Cannot destroy the stage directory while it is being applied to the active directory.';
    $assert_session->pageTextContains($destroy_error);
    $assert_session->pageTextNotContains($cancelled_message);

    // We should get the same error if we log in as another user and try to
    // delete the staged update.
    $user = $this->createUser([
      'administer software updates',
      'access site in maintenance mode',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/reports/updates/update');
    $assert_session->pageTextContains($conflict_message);
    $page->pressButton('Delete existing update');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($destroy_error);
    $assert_session->pageTextNotContains('Staged update deleted');

    // Two hours later, Package Manager should consider the stage to be stale,
    // allowing the staged update to be deleted.
    TestTime::setFakeTimeByOffset('+2 hours');
    $this->getSession()->reload();
    $assert_session->pageTextContains($conflict_message);
    $page->pressButton('Delete existing update');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Staged update deleted');

    // If a legitimate error is raised during pre-apply, we should be able to
    // delete the staged update right away.
    $results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($results, PreApplyEvent::class);
    // Before pressing the button, create a new stage fixture manipulator.
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.1');
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $page->clickLink('the error page');
    $page->pressButton('Cancel update');
    $assert_session->pageTextContains($cancelled_message);
  }

}
