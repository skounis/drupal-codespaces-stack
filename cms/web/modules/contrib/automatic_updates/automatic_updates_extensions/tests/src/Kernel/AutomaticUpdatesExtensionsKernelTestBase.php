<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Kernel;

use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\Validator\ComposerValidator;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * Base class for kernel tests of the Automatic Updates Extensions module.
 *
 * @internal
 *   This class is an internal part of the module's testing infrastructure and
 *   should not be used by external code.
 */
abstract class AutomaticUpdatesExtensionsKernelTestBase extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates_extensions',
    'package_manager_test_release_history',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Disable the Composer executable validator, since it may cause the tests
    // to fail if a supported version of Composer is unavailable to the web
    // server. This should be okay in most situations because, apart from the
    // validator, only Composer Stager needs run Composer, and
    // package_manager_bypass is disabling those operations.
    $this->disableValidators[] = ComposerValidator::class;
    parent::setUp();
    // Install additional packages that will be needed in tests.
    (new ActiveFixtureManipulator())
      ->addPackage([
        "name" => "drupal/my_module",
        "version" => "9.8.0",
        "type" => "drupal-module",
      ])
      ->addPackage([
        "name" => "drupal/contrib_profile1",
        "version" => "1.0.0",
        "type" => "drupal-profile",
      ])
      ->addPackage([
        "name" => "drupal/my_dev_module",
        "version" => "9.8.1",
        "type" => "drupal-module",
      ], TRUE)
      ->addPackage([
        "name" => "drupal/semver_test",
        "version" => "8.1.0",
        "type" => "drupal-module",
      ])
      ->addPackage([
        "name" => "drupal/aaa_update_test",
        "version" => "1.0.0",
        "type" => "drupal-module",
      ])
      ->addPackage([
        "name" => "drupal/package_manager_test_update",
        "version" => "1.0.0",
        "type" => "drupal-module",
      ])->commitChanges();
  }

  /**
   * Asserts validation results are returned from a stage life cycle event.
   *
   * @param string[] $project_versions
   *   The project versions.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param string|null $event_class
   *   (optional) The class of the event which should return the results. Must
   *   be passed if $expected_results is not empty.
   */
  protected function assertUpdateResults(array $project_versions, array $expected_results, ?string $event_class = NULL): void {
    $stage = $this->container->get(ExtensionUpdateStage::class);

    try {
      $stage->begin($project_versions);
      $stage->stage();
      $stage->apply();
      $stage->postApply();
      $stage->destroy();

      // If we did not get an exception, ensure we didn't expect any results.
      $this->assertEmpty($expected_results);
    }
    catch (StageEventException $e) {
      $this->assertNotEmpty($expected_results);
      $exception_event = $e->event;
      $this->assertInstanceOf($event_class, $exception_event);
      $this->assertInstanceOf(PreOperationStageEvent::class, $exception_event);
      $this->assertValidationResultsEqual($expected_results, $e->event->getResults());
    }
  }

}
