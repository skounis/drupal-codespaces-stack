<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Kernel\Validator;

use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates_extensions\Kernel\AutomaticUpdatesExtensionsKernelTestBase;

/**
 * @coversDefaultClass \Drupal\automatic_updates_extensions\Validator\RequestedUpdateValidator
 * @group automatic_updates_extensions
 * @internal
 */
class RequestedUpdateValidatorTest extends AutomaticUpdatesExtensionsKernelTestBase {

  /**
   * Tests error messages if requested updates were not staged.
   *
   * @param array $staged_versions
   *   An array of the staged versions where the keys are the package names and
   *   the values are the package versions.
   * @param array $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerTestErrorMessage
   */
  public function testErrorMessage(array $staged_versions, array $expected_results): void {
    if ($staged_versions) {
      // If we are going to stage updates to Drupal packages also update a
      // non-Drupal. The validator should ignore the non-Drupal packages.
      (new ActiveFixtureManipulator())
        ->addPackage([
          "name" => 'vendor/non-drupal-package',
          "version" => "1.0.0",
          "type" => "drupal-module",
        ])
        ->commitChanges();
      $this->getStageFixtureManipulator()->setVersion('vendor/non-drupal-package', '1.0.1');
      foreach ($staged_versions as $package => $version) {
        $this->getStageFixtureManipulator()->setVersion($package, $version);
      }
    }

    $this->setReleaseMetadata([
      'semver_test' => __DIR__ . '/../../../fixtures/release-history/semver_test.1.1.xml',
      'drupal' => __DIR__ . '/../../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml',
      'aaa_update_test' => __DIR__ . "/../../../../../package_manager/tests/fixtures/release-history/aaa_update_test.1.1.xml",
    ]);
    // Set the project version to '8.0.1' so that there 2 versions of above this
    // that will be in the list of supported releases, 8.1.0 and 8.1.1.
    (new ActiveFixtureManipulator())
      ->setVersion('drupal/semver_test', '8.0.1')
      ->commitChanges();
    // @todo Replace with use of the trait from the Update module in https://drupal.org/i/3348234.
    $module_info = ['version' => '8.0.1', 'project' => 'semver_test'];
    $this->config('update_test.settings')
      ->set("system_info.semver_test", $module_info)
      ->save();

    $stage = $this->container->get(ExtensionUpdateStage::class);
    $stage->begin([
      'semver_test' => '8.1.1',
      'aaa_update_test' => '8.x-1.1',
    ]);
    $stage->stage();
    $this->assertStatusCheckResults($expected_results, $stage);
    try {
      $stage->apply();
      $this->fail('Expecting an exception.');
    }
    catch (StageEventException $exception) {
      $this->assertExpectedResultsFromException($expected_results, $exception);
    }
  }

  /**
   * Data provider for testErrorMessage().
   *
   * @return mixed[]
   *   The test cases.
   */
  public static function providerTestErrorMessage() {
    return [
      'no updates' => [
        [],
        [
          ValidationResult::createError([t('No updates detected in the staging area.')]),
        ],
      ],
      '1 project not updated' => [
        [
          'drupal/aaa_update_test' => '1.1.0',
        ],
        [
          ValidationResult::createError([t("The requested update to 'drupal/semver_test' to version '8.1.1' was not performed.")]),
        ],
      ],
      'project updated to wrong version' => [
        [
          'drupal/semver_test' => '8.1.0',
          'drupal/aaa_update_test' => '1.1.0',
        ],
        [
          ValidationResult::createError([t("The requested update to 'drupal/semver_test' to version '8.1.1' does not match the actual staged update to '8.1.0'.")]),
        ],
      ],
    ];
  }

}
