<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\package_manager_bypass\LoggingBeginner;
use Drupal\package_manager_bypass\LoggingCommitter;
use Drupal\package_manager_bypass\NoOpStager;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;
use PhpTuf\ComposerStager\API\Exception\LogicException;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
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
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->drupalGet('/admin/modules/update');
    $page->hasButton('Update to 9.8.1');

    // Make the specified Composer Stager operation class throw an exception.
    $message = $this->createComposeStagerMessage("Failure from inside $service_class");
    $exception = new $exception_class($message);
    call_user_func([$service_class, 'setException'], $exception);

    // Start the update.
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    // We can't continue the update after an error in the committer.
    if ($service_class === LoggingCommitter::class) {
      $page->pressButton('Continue');
      $this->checkForMetaRefresh();
      $this->clickLink('the error page');
      $assert_session->statusCodeEquals(200);
      $assert_session->statusMessageContains('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
      return;
    }
    $this->clickLink('the error page');
    $assert_session->statusMessageContains($exception->getMessage());

    // Make the same Composer Stager operation class NOT throw an exception.
    call_user_func([$service_class, 'setException'], NULL);

    // Set up the update to 9.8.1 again as the stage gets destroyed after an
    // exception occurs.
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    // Stage should be automatically deleted when an error occurs.
    $assert_session->buttonNotExists('Delete existing update');
    // This ensures that we can still update after the exception no longer
    // exists.
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
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
