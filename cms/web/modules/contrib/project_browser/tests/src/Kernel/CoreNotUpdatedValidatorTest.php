<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\project_browser\ComposerInstaller\Installer;

/**
 * @covers \Drupal\project_browser\ComposerInstaller\Validator\CoreNotUpdatedValidator
 *
 * @group project_browser
 */
final class CoreNotUpdatedValidatorTest extends PackageManagerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'org/package-name',
        'version' => '1.0.0',
        'type' => 'drupal-module',
        // We add a package and then immediately remove it to get a repository
        // entry for it, so we can 'composer require' it later.
      ])
      ->removePackage('org/package-name')
      ->commitChanges();
  }

  /**
   * Data provider for testPreApplyException().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerPreApplyException(): array {
    return [
      'core updated' => [
        TRUE,
        [ValidationResult::createError([t('Drupal core has been updated in the staging area, which is not allowed by Project Browser.')])],
      ],
      'core not updated' => [
        FALSE,
        [],
      ],
    ];
  }

  /**
   * Tests core was not updated during a project install.
   *
   * @param bool $core_updated
   *   Whether drupal/core was updated.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation result.
   *
   * @throws \Exception
   *
   * @dataProvider providerPreApplyException
   */
  public function testPreApplyException(bool $core_updated, array $expected_results): void {
    if ($core_updated) {
      $this->getStageFixtureManipulator()?->setCorePackageVersion('9.8.1');
    }
    /** @var \Drupal\project_browser\ComposerInstaller\Installer $installer */
    $installer = $this->container->get(Installer::class);
    $installer->create();
    $installer->require(['org/package-name']);
    try {
      $installer->apply();
      // If we did not get an exception, ensure we didn't expect any results.
      $this->assertEmpty($expected_results);
    }
    catch (StageEventException $e) {
      assert($e->event instanceof PreOperationStageEvent);
      $this->assertValidationResultsEqual($expected_results, $e->event->getResults());
    }
  }

}
