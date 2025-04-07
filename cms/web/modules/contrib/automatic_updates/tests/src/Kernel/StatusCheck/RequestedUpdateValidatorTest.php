<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\UpdateStage;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @coversDefaultClass \Drupal\automatic_updates\Validator\RequestedUpdateValidator
 * @group automatic_updates
 * @internal
 */
class RequestedUpdateValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * Tests error message is shown if the core version is not updated.
   */
  public function testErrorMessageOnCoreNotUpdated(): void {
    // Update `drupal/core-recommended` to a version that does not match the
    // requested version of '9.8.1'. This also does not update all packages that
    // are expected to be updated when updating Drupal core.
    // @see \Drupal\automatic_updates\UpdateStage::begin()
    // @see \Drupal\package_manager\InstalledPackagesList::getCorePackages()
    $this->getStageFixtureManipulator()->setVersion('drupal/core-recommended', '9.8.2');
    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
    $this->container->get('module_installer')->install(['automatic_updates']);

    $stage = $this->container->get(UpdateStage::class);
    $expected_results = [
      ValidationResult::createError([t("The requested update to 'drupal/core-recommended' to version '9.8.1' does not match the actual staged update to '9.8.2'.")]),
      ValidationResult::createError([t("The requested update to 'drupal/core-dev' to version '9.8.1' was not performed.")]),
    ];
    $stage->begin(['drupal' => '9.8.1']);
    $this->assertStatusCheckResults($expected_results, $stage);
    $stage->stage();
    try {
      $stage->apply();
      $this->fail('Expecting an exception.');
    }
    catch (StageEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

  /**
   * Tests error message is shown if there are no core packages in stage.
   */
  public function testErrorMessageOnEmptyCorePackages(): void {
    $this->getStageFixtureManipulator()
      ->removePackage('drupal/core')
      ->removePackage('drupal/core-recommended')
      ->removePackage('drupal/core-dev', TRUE);

    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
    $this->container->get('module_installer')->install(['automatic_updates']);

    $expected_results = [
      ValidationResult::createError([t('No updates detected in the staging area.')]),
    ];
    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $this->assertStatusCheckResults($expected_results, $stage);
    $stage->stage();
    try {
      $stage->apply();
      $this->fail('Expecting an exception.');
    }
    catch (StageEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

}
