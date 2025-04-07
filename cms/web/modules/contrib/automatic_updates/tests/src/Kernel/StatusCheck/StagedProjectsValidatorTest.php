<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\UpdateStage;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager\Validator\SupportedReleaseValidator;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @covers \Drupal\automatic_updates\Validator\StagedProjectsValidator
 * @group automatic_updates
 * @internal
 */
class StagedProjectsValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // In this test, we don't care whether the updated projects are secure and
    // supported.
    $this->disableValidators[] = SupportedReleaseValidator::class;
    parent::setUp();
  }

  /**
   * Tests that an error is raised if Drupal extensions are unexpectedly added.
   */
  public function testProjectsAdded(): void {
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'drupal/test-module',
        'version' => '1.3.0',
        'type' => 'drupal-module',
      ])
      ->addPackage([
        'name' => 'other/removed',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'drupal/dev-test-module',
          'version' => '1.3.0',
          'type' => 'drupal-module',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'other/dev-removed',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->commitChanges();

    $stage_manipulator = $this->getStageFixtureManipulator();
    $stage_manipulator
      ->setCorePackageVersion('9.8.1')
      ->addPackage([
        'name' => 'drupal/test-module2',
        'version' => '1.3.1',
        'type' => 'drupal-module',
      ])
      ->addPackage(
        [
          'name' => 'drupal/dev-test-module2',
          'version' => '1.3.1',
          'type' => 'drupal-custom-module',
        ],
        TRUE
      )
      // The validator shouldn't complain about these packages being added or
      // removed, since it only cares about Drupal modules and themes.
      ->addPackage([
        'name' => 'other/new_project',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'other/dev-new_project',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->removePackage('other/removed')
      ->removePackage('other/dev-removed', TRUE);

    $messages = [
      t("custom module 'drupal/dev-test-module2' installed."),
      t("module 'drupal/test-module2' installed."),
    ];
    $error = ValidationResult::createError($messages, t('The update cannot proceed because the following Drupal projects were installed during the update.'));

    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $stage->stage();
    try {
      $stage->apply();
      $this->fail('Expected an error, but none was raised.');
    }
    catch (StageEventException $e) {
      $this->assertExpectedResultsFromException([$error], $e);
    }
  }

  /**
   * Tests that errors are raised if Drupal extensions are unexpectedly removed.
   */
  public function testProjectsRemoved(): void {
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'drupal/test_theme',
        'version' => '1.3.0',
        'type' => 'drupal-theme',
      ])
      ->addPackage([
        'name' => 'drupal/test-module2',
        'version' => '1.3.1',
        'type' => 'drupal-module',
      ])
      ->addPackage([
        'name' => 'other/removed',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'drupal/dev-test_theme',
          'version' => '1.3.0',
          'type' => 'drupal-custom-theme',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'drupal/dev-test-module2',
          'version' => '1.3.1',
          'type' => 'drupal-module',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'other/dev-removed',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->commitChanges();

    $stage_manipulator = $this->getStageFixtureManipulator();
    $stage_manipulator->removePackage('drupal/test_theme')
      ->removePackage('drupal/dev-test_theme', TRUE)
    // The validator shouldn't complain about these packages being removed,
    // since it only cares about Drupal modules and themes.
      ->removePackage('other/removed')
      ->removePackage('other/dev-removed', TRUE)
      ->setCorePackageVersion('9.8.1');

    $messages = [
      t("custom theme 'drupal/dev-test_theme' removed."),
      t("theme 'drupal/test_theme' removed."),
    ];
    $error = ValidationResult::createError($messages, t('The update cannot proceed because the following Drupal projects were removed during the update.'));
    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $stage->stage();
    try {
      $stage->apply();
      $this->fail('Expected an error, but none was raised.');
    }
    catch (StageEventException $e) {
      $this->assertExpectedResultsFromException([$error], $e);
    }
  }

  /**
   * Tests that errors are raised if Drupal extensions are unexpectedly updated.
   */
  public function testVersionsChanged(): void {
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'drupal/test-module',
        'version' => '1.3.0',
        'type' => 'drupal-module',
      ])
      ->addPackage([
        'name' => 'other/changed',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'drupal/dev-test-module',
          'version' => '1.3.0',
          'type' => 'drupal-module',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'other/dev-changed',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->commitChanges();

    $stage_manipulator = $this->getStageFixtureManipulator();
    $stage_manipulator->setVersion('drupal/test-module', '1.3.1')
      ->setVersion('drupal/dev-test-module', '1.3.1')
    // The validator shouldn't complain about these packages being updated,
    // because it only cares about Drupal modules and themes.
      ->setVersion('other/changed', '1.3.2')
      ->setVersion('other/dev-changed', '1.3.2')
      ->setCorePackageVersion('9.8.1');

    $messages = [
      t("module 'drupal/dev-test-module' from 1.3.0 to 1.3.1."),
      t("module 'drupal/test-module' from 1.3.0 to 1.3.1."),
    ];
    $error = ValidationResult::createError($messages, t('The update cannot proceed because the following Drupal projects were unexpectedly updated. Only Drupal Core updates are currently supported.'));
    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $stage->stage();

    try {
      $stage->apply();
      $this->fail('Expected an error, but none was raised.');
    }
    catch (StageEventException $e) {
      $this->assertExpectedResultsFromException([$error], $e);
    }
  }

  /**
   * Tests that no errors occur if only core and its dependencies are updated.
   */
  public function testNoErrors(): void {
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'drupal/test-module',
        'version' => '1.3.0',
        'type' => 'drupal-module',
      ])
      ->addPackage([
        'name' => 'other/removed',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage([
        'name' => 'other/changed',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'drupal/dev-test-module',
          'version' => '1.3.0',
          'type' => 'drupal-module',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'other/dev-removed',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->addPackage(
        [
          'name' => 'other/dev-changed',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->commitChanges();

    $stage_manipulator = $this->getStageFixtureManipulator();
    $stage_manipulator->setCorePackageVersion('9.8.1')
    // The validator shouldn't care what happens to these packages, since it
    // only concerns itself with Drupal modules and themes.
      ->addPackage([
        'name' => 'other/new_project',
        'version' => '1.3.1',
        'type' => 'library',
      ])
      ->addPackage(
        [
          'name' => 'other/dev-new_project',
          'version' => '1.3.1',
          'type' => 'library',
        ],
        TRUE
      )
      ->setVersion('other/changed', '1.3.2')
      ->setVersion('other/dev-changed', '1.3.2')
      ->removePackage('other/removed')
      ->removePackage('other/dev-removed', TRUE);

    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $stage->stage();
    $stage->apply();
    $this->assertTrue(TRUE);
  }

}
