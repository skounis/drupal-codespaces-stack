<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Functional;

use Drupal\package_manager_bypass\LoggingBeginner;
use Drupal\package_manager_bypass\LoggingCommitter;
use Drupal\package_manager_bypass\NoOpStager;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;
use PhpTuf\ComposerStager\API\Exception\LogicException;

/**
 * @covers \Drupal\automatic_updates_extensions\Form\UpdaterForm
 * @group automatic_updates_extensions
 * @internal
 */
class ComposerStagerOperationFailureTest extends UpdaterFormTestBase {

  /**
   * Tests Composer operation failure is handled properly.
   *
   * @param string $exception_class
   *   The exception class.
   * @param string $service_class
   *   The Composer Stager service which should throw an exception.
   *
   * @dataProvider providerComposerOperationFailure
   */
  public function testComposerOperationFailure(string $exception_class, string $service_class): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->setReleaseMetadata(__DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml');
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/semver_test.1.1.xml');
    $this->setProjectInstalledVersion(['semver_test' => '8.1.0']);
    $this->checkForUpdates();
    // Navigate to the update form.
    $this->drupalGet('/admin/reports/updates');
    $this->clickLink('Update Extensions');
    $this->assertTableShowsUpdates(
      'Semver Test',
      '8.1.0',
      '8.1.1',
    );
    $this->getStageFixtureManipulator()->setVersion('drupal/semver_test_package_name', '8.1.1');
    $this->assertUpdatesCount(1);
    $page->checkField('projects[semver_test]');

    // Make the specified Composer Stager operation class throw an exception.
    $message = $this->createComposeStagerMessage("Failure from inside $service_class");
    $exception = new $exception_class($message);
    call_user_func([$service_class, 'setException'], $exception);

    // Start the update.
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    // We can't continue the update after an error in the committer.
    if ($service_class === LoggingCommitter::class) {
      $this->acceptWarningAndUpdate();
      $this->clickLink('the error page');
      $assert_session->statusCodeEquals(200);
      $assert_session->statusMessageContains('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
      return;
    }
    $this->clickLink('the error page');
    $assert_session->statusMessageContains($exception->getMessage());

    // Make the same Composer Stager operation class NOT throw an exception.
    call_user_func([$service_class, 'setException'], NULL);

    // Stage should be automatically deleted when an error occurs.
    $assert_session->buttonNotExists('Delete existing update');
    // This ensures that we can still update after the exception no longer
    // exists.
    $page->checkField('projects[semver_test]');
    $this->getStageFixtureManipulator()->setVersion('drupal/semver_test_package_name', '8.1.1');
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    $this->acceptWarningAndUpdate();
    $assert_session->statusMessageContains('Update complete!');
  }

  /**
   * Data provider for testComposerOperationFailure().
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerComposerOperationFailure(): array {
    return [
      'LogicException from Beginner' => [LogicException::class, LoggingBeginner::class],
      'LogicException from Stager' => [LogicException::class, NoOpStager::class],
      'InvalidArgumentException from Stager' => [InvalidArgumentException::class, NoOpStager::class],
      'LogicException from Committer' => [LogicException::class, LoggingCommitter::class],
    ];
  }

}
