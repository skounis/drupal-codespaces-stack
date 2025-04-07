<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Kernel\Validator;

use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates_extensions\Kernel\AutomaticUpdatesExtensionsKernelTestBase;

/**
 * @coversDefaultClass \Drupal\automatic_updates_extensions\Validator\ForbidCoreChangesValidator
 * @group automatic_updates_extensions
 * @internal
 */
class ForbidCoreChangesValidatorTest extends AutomaticUpdatesExtensionsKernelTestBase {

  /**
   * Tests error messages if requested updates were not staged.
   *
   * @param array $staged_versions
   *   An array of the staged versions where the keys are the package names and
   *   the values are the package versions or NULL if the package should be
   *   removed in the stage.
   * @param string[][] $new_packages
   *   An array of the new packages to add to the stage.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerTestErrorMessage
   */
  public function testErrorMessages(array $staged_versions, array $new_packages, array $expected_results): void {
    $this->setReleaseMetadata([
      'semver_test' => __DIR__ . '/../../../fixtures/release-history/semver_test.1.1.xml',
      'drupal' => __DIR__ . '/../../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml',
    ]);
    $this->getStageFixtureManipulator()->addPackage([
      'name' => 'drupal/non-core',
      'version' => '1.0.0',
      'type' => 'package',
    ]);

    foreach ($staged_versions as $package => $version) {
      if ($version === NULL) {
        $this->getStageFixtureManipulator()->removePackage($package);
        continue;
      }
      $this->getStageFixtureManipulator()->setVersion($package, $version);
    }
    foreach ($new_packages as $package) {
      $this->getStageFixtureManipulator()->addPackage($package);
    }
    $this->getStageFixtureManipulator()->setVersion('drupal/semver_test', '8.1.1');

    $stage = $this->container->get(ExtensionUpdateStage::class);
    $stage->begin([
      'semver_test' => '8.1.1',
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
  public static function providerTestErrorMessage(): array {
    $summary = t('Updating Drupal Core while updating extensions is currently not supported. Use <a href="/admin/reports/updates/update">this form</a> to update Drupal core. The following changes were made to the Drupal core packages:');
    return [
      'drupal/core updated, non-core updated' => [
        [
          'drupal/core' => '9.8.1',
          'drupal/non-core' => '1.0.1',
        ],
        [],
        [ValidationResult::createError([t("'drupal/core' version changed from 9.8.0 to 9.8.1.")], $summary)],
      ],
      'drupal/core-recommended and drupal/core updated, non-core package installed' => [
        [
          'drupal/core-recommended' => '9.8.1',
          'drupal/core' => '9.8.1',
        ],
        [
          [
            'name' => 'other-org/other-package',
            'type' => 'package',
          ],
        ],
        [
          ValidationResult::createError(
            [
              t("'drupal/core-recommended' version changed from 9.8.0 to 9.8.1."),
              t("'drupal/core' version changed from 9.8.0 to 9.8.1."),
            ],
            $summary
          ),
        ],
      ],
      'drupal/core-recommended removed, drupal/core updated, drupal/core-composer-scaffold installed, non-core package removed' => [
        [
          'drupal/core-recommended' => NULL,
          'drupal/core' => '9.8.1',
          'drupal/non-core' => NULL,
        ],
        [
          [
            'name' => 'drupal/core-composer-scaffold',
            'type' => 'package',
          ],
        ],
        [
          ValidationResult::createError(
            [
              t("'drupal/core-composer-scaffold' installed."),
              t("'drupal/core-recommended' removed."),
              t("'drupal/core' version changed from 9.8.0 to 9.8.1."),
            ],
            $summary
          ),
        ],
      ],
    ];
  }

}
