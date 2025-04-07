<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\CommandExecutor;
use Drupal\automatic_updates\UpdateStage;
use Drupal\fixture_manipulator\StageFixtureManipulator;
use Drupal\Tests\automatic_updates\Traits\TestSetUpTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;
use Drupal\Tests\package_manager\Traits\ComposerStagerTestTrait;
use Drupal\Tests\package_manager\Traits\FixtureManipulatorTrait;

/**
 * Base class for functional tests of the Automatic Updates module.
 *
 * @internal
 */
abstract class AutomaticUpdatesFunctionalTestBase extends BrowserTestBase {

  use AssertPreconditionsTrait;
  use ComposerStagerTestTrait;
  use FixtureManipulatorTrait;
  use TestSetUpTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'package_manager_bypass',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::SECURITY)
      ->save();
    $this->mockActiveCoreVersion('9.8.0');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    StageFixtureManipulator::handleTearDown();
    $service_ids = [
      // If automatic_updates is installed, ensure any stage directory created
      // during the test is cleaned up.
      UpdateStage::class,
    ];
    foreach ($service_ids as $service_id) {
      if ($this->container->has($service_id)) {
        $this->container->get($service_id)->destroy(TRUE);
      }
    }
    parent::tearDown();
  }

  /**
   * Mocks the current (running) version of core, as known to the Update module.
   *
   * @todo Remove this function with use of the trait from the Update module in
   *   https://drupal.org/i/3348234.
   *
   * @param string $version
   *   The version of core to mock.
   */
  protected function mockActiveCoreVersion(string $version): void {
    $this->config('update_test.settings')
      ->set('system_info.#all.version', $version)
      ->save();
  }

  /**
   * Checks for available updates.
   *
   * Assumes that a user with appropriate permissions is logged in.
   */
  protected function checkForUpdates(): void {
    $this->drupalGet('/admin/reports/updates');
    $this->getSession()->getPage()->clickLink('Check manually');
    $this->checkForMetaRefresh();
  }

  /**
   * Asserts that we are on the "update ready" form.
   *
   * @param string $target_version
   *   The target version of Drupal core.
   */
  protected function assertUpdateReady(string $target_version): void {
    $assert_session = $this->assertSession();
    $assert_session->addressMatches('/\/admin\/automatic-update-ready\/[a-zA-Z0-9_\-]+$/');
    $assert_session->pageTextContainsOnce('Drupal core will be updated to ' . $target_version);
    $button = $assert_session->buttonExists("Continue");
    $this->assertTrue($button->hasClass('button--primary'));
  }

  /**
   * Runs the console update command, which will trigger status checks.
   */
  protected function runConsoleUpdateCommand(): void {
    // Ensure that a valid test user agent cookie has been generated.
    $this->prepareRequest();

    $this->container->get(CommandExecutor::class)
      ->create('--is-from-web')
      ->setEnv([
        // Ensure that the command will boot up and run in the test site.
        // @see drupal_valid_test_ua()
        'HTTP_USER_AGENT' => $this->getSession()->getCookie('SIMPLETEST_USER_AGENT'),
      ])
      ->setWorkingDirectory($this->getDrupalRoot())
      ->mustRun();
  }

}
