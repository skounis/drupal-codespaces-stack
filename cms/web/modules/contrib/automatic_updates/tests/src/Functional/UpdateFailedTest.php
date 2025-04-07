<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\package_manager_bypass\LoggingCommitter;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class UpdateFailedTest extends UpdaterFormTestBase {

  /**
   * Tests that an exception is thrown if a previous apply failed.
   */
  public function testMarkerFileFailure(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();

    $this->drupalGet('/admin/modules/update');
    $assert_session->pageTextNotContains(static::$errorsExplanation);
    $assert_session->pageTextNotContains(static::$warningsExplanation);
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);

    LoggingCommitter::setException(\Exception::class, 'failed at committer');
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $failure_message = 'Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.';
    $assert_session->pageTextContainsOnce('An error has occurred.');
    $assert_session->pageTextContains($failure_message);
    $page->clickLink('the error page');

    // We should be on the form (i.e., 200 response code), but unable to
    // continue the update.
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($failure_message);
    $assert_session->buttonNotExists('Continue');
    // The same thing should be true if we try to start from the beginning.
    $this->drupalGet('/admin/modules/update');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($failure_message);
    $assert_session->buttonNotExists('Update');
  }

  /**
   * Tests what happens when a staged update is deleted without being destroyed.
   */
  public function testStagedUpdateDeletedImproperly(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();

    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/update');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    $this->assertUpdateReady('9.8.1');
    // Confirm if the staged directory is deleted without using destroy(), then
    // an error message will be displayed on the page.
    // @see \Drupal\package_manager\Stage::getStagingRoot()
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = $this->container->get('file_system');
    $dir = $file_system->getTempDirectory() . '/.package_manager' . $this->config('system.site')->get('uuid');
    $this->assertDirectoryExists($dir);
    $file_system->deleteRecursive($dir);
    $this->getSession()->reload();
    $assert_session = $this->assertSession();
    $error_message = 'There was an error loading the pending update. Press the Cancel update button to start over.';
    $assert_session->pageTextContainsOnce($error_message);
    // We should be able to start over without any problems, and the error
    // message should not be seen on the updater form.
    $page->pressButton('Cancel update');
    $assert_session->addressEquals('/admin/reports/updates/update');
    $assert_session->pageTextNotContains($error_message);
    $assert_session->pageTextContains('The update was successfully cancelled.');
    $assert_session->buttonExists('Update');
  }

}
