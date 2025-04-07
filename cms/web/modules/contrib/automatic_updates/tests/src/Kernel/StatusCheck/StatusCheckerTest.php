<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\ConsoleUpdateStage;
use Drupal\automatic_updates\UpdateStage;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\automatic_updates\Validator\StagedProjectsValidator;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\automatic_updates_test_status_checker\EventSubscriber\TestSubscriber2;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\system\SystemManager;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @coversDefaultClass \Drupal\automatic_updates\Validation\StatusChecker
 * @group automatic_updates
 * @internal
 */
class StatusCheckerTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates_test',
    'package_manager_test_validation',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setCoreVersion('9.8.2');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);

    // Undoes the override in parent::setUp(), to allow the module to be
    // installed, which every other test methods in this class does. Without
    // this \Drupal\Core\Config\PreExistingConfigException is thrown.
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $this->config('automatic_updates.settings')->delete();
  }

  /**
   * @covers ::getResults
   */
  public function testGetResults(): void {
    $this->container->get('module_installer')
      ->install(['automatic_updates', 'automatic_updates_test_status_checker']);
    $this->assertCheckerResultsFromManager([], TRUE);
    $checker_1_expected = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    $checker_2_expected = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_expected, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_expected, StatusCheckEvent::class);
    $expected_results_all = array_merge($checker_1_expected, $checker_2_expected);
    $this->assertCheckerResultsFromManager($expected_results_all, TRUE);

    // Define a constant flag that will cause the status checker service
    // priority to be altered.
    define('PACKAGE_MANAGER_TEST_VALIDATOR_PRIORITY', 1);
    // Rebuild the container to trigger the service to be altered.
    $kernel = $this->container->get('kernel');
    $this->container = $kernel->rebuildContainer();
    // The stored results should be returned, even though the validators' order
    // has been changed and the container has been rebuilt.
    $this->assertValidationResultsEqual($expected_results_all, $this->getResultsFromManager());
    // Confirm that after calling run() the expected results order has changed.
    $expected_results_all_reversed = array_reverse($expected_results_all);
    $this->assertCheckerResultsFromManager($expected_results_all_reversed, TRUE);

    $checker_1_expected = [
      'checker 1 errors' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
      'checker 1 warnings' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    $checker_2_expected = [
      'checker 2 errors' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
      'checker 2 warnings' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($checker_1_expected, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_expected, StatusCheckEvent::class);
    $expected_results_all = array_merge($checker_2_expected, $checker_1_expected);
    $this->assertCheckerResultsFromManager($expected_results_all, TRUE);

    // Confirm that filtering by severity works.
    $warnings_only_results = [
      $checker_2_expected['checker 2 warnings'],
      $checker_1_expected['checker 1 warnings'],
    ];
    $this->assertCheckerResultsFromManager($warnings_only_results, FALSE, SystemManager::REQUIREMENT_WARNING);

    $errors_only_results = [
      $checker_2_expected['checker 2 errors'],
      $checker_1_expected['checker 1 errors'],
    ];
    $this->assertCheckerResultsFromManager($errors_only_results, FALSE, SystemManager::REQUIREMENT_ERROR);
  }

  /**
   * Tests that the manager is run after modules are installed.
   */
  public function testRunOnInstall(): void {
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    // Confirm that messages from an existing module are displayed when
    // 'automatic_updates' is installed.
    $this->container->get('module_installer')->install(['automatic_updates']);
    $this->assertCheckerResultsFromManager($checker_1_results);

    // Confirm that the checkers are run when a module that provides a status
    // checker is installed.
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    $checker_2_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->install(['automatic_updates_test_status_checker']);
    $expected_results_all = array_merge($checker_1_results, $checker_2_results);
    $this->assertCheckerResultsFromManager($expected_results_all);

    // Confirm that the checkers are run when a module that does not provide a
    // status checker is installed.
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    $checker_2_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_results, StatusCheckEvent::class);
    $expected_results_all = array_merge($checker_1_results, $checker_2_results);
    $this->container->get('module_installer')->install(['help']);
    $this->assertCheckerResultsFromManager($expected_results_all);
  }

  /**
   * Tests that the manager is run after modules are uninstalled.
   */
  public function testRunOnUninstall(): void {
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    $checker_2_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_results, StatusCheckEvent::class);
    // Confirm that messages from existing modules are displayed when
    // 'automatic_updates' is installed.
    $this->container->get('module_installer')->install(['automatic_updates', 'automatic_updates_test_status_checker', 'help']);
    $expected_results_all = array_merge($checker_1_results, $checker_2_results);
    $this->assertCheckerResultsFromManager($expected_results_all);

    // Confirm that the checkers are run when a module that provides a status
    // checker is uninstalled.
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    $checker_2_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    TestSubscriber2::setTestResult($checker_2_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->uninstall(['automatic_updates_test_status_checker']);
    $this->assertCheckerResultsFromManager($checker_1_results);

    // Confirm that the checkers are run when a module that does not provide a
    // status checker is uninstalled.
    $checker_1_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($checker_1_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->uninstall(['help']);
    $this->assertCheckerResultsFromManager($checker_1_results);
  }

  /**
   * @covers ::runIfNoStoredResults
   * @covers ::clearStoredResults
   */
  public function testRunIfNeeded(): void {
    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->install(['automatic_updates', 'automatic_updates_test_status_checker']);
    $this->assertCheckerResultsFromManager($expected_results);

    $unexpected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($unexpected_results, StatusCheckEvent::class);
    $manager = $this->container->get(StatusChecker::class);
    // Confirm that the new results will not be returned because the checkers
    // will not be run.
    $manager->runIfNoStoredResults();
    $this->assertCheckerResultsFromManager($expected_results);

    // Confirm that the new results will be returned because the checkers will
    // be run if the stored results are deleted.
    $manager->clearStoredResults();
    $expected_results = $unexpected_results;
    $manager->runIfNoStoredResults();
    $this->assertCheckerResultsFromManager($expected_results);

    // Confirm that the results are the same after rebuilding the container.
    $unexpected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($unexpected_results, StatusCheckEvent::class);
    /** @var \Drupal\Core\DrupalKernel $kernel */
    $kernel = $this->container->get('kernel');
    $this->container = $kernel->rebuildContainer();
    $this->assertCheckerResultsFromManager($expected_results);
  }

  /**
   * Tests the Automatic Updates cron setting changes which stage class is used.
   */
  public function testCronSetting(): void {
    $this->enableModules(['automatic_updates']);
    $stage = NULL;
    $listener = function (StatusCheckEvent $event) use (&$stage): void {
      $stage = $event->stage;
    };
    $this->addEventTestListener($listener, StatusCheckEvent::class);
    $this->container->get(StatusChecker::class)->run();
    // By default, updates will be enabled on cron.
    $this->assertInstanceOf(ConsoleUpdateStage::class, $stage);
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::DISABLED)
      ->save();
    $this->container->get(StatusChecker::class)->run();
    $this->assertInstanceOf(UpdateStage::class, $stage);
  }

  /**
   * Tests that stored validation results are deleted after an update.
   */
  public function testStoredResultsDeletedPostApply(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
    $this->container->get('module_installer')->install(['automatic_updates']);

    // The status checker should raise a warning, so that the update is not
    // blocked or aborted.
    $results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING)];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);

    // Ensure that the validation manager collects the warning.
    $manager = $this->container->get(StatusChecker::class)
      ->run();
    $this->assertValidationResultsEqual($results, $manager->getResults());
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);
    // Even though the checker no longer returns any results, the previous
    // results should be stored.
    $this->assertValidationResultsEqual($results, $manager->getResults());

    // Don't validate staged projects because actual stage operations are
    // bypassed by package_manager_bypass, which will make this validator
    // complain that there is no actual Composer data for it to inspect.
    $validator = $this->container->get(StagedProjectsValidator::class);
    $this->container->get('event_dispatcher')->removeSubscriber($validator);

    $stage = $this->container->get(UpdateStage::class);
    $stage->begin(['drupal' => '9.8.1']);
    $stage->stage();
    $stage->apply();
    $stage->postApply();
    $stage->destroy();

    // The status validation manager shouldn't have any stored results.
    $this->assertEmpty($manager->getResults());
  }

  /**
   * Tests that certain config changes clear stored results.
   */
  public function testStoredResultsClearedOnConfigChanges(): void {
    $this->container->get('module_installer')->install(['automatic_updates']);

    $results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->assertCheckerResultsFromManager($results, TRUE);
    // The results should be stored.
    $this->assertCheckerResultsFromManager($results, FALSE);
    // Changing the configured path to rsync should not clear the results.
    $this->config('package_manager.settings')
      ->set('executables.rsync', '/path/to/rsync')
      ->save();
    $this->assertCheckerResultsFromManager($results, FALSE);
    // Changing the configured path to Composer should clear the results.
    $this->config('package_manager.settings')
      ->set('executables.composer', '/path/to/composer')
      ->save();
    $this->assertNull($this->getResultsFromManager(FALSE));
  }

  /**
   * @covers ::getLastRunTime
   */
  public function testLastRunTime(): void {
    $this->enableModules(['automatic_updates']);

    /** @var \Drupal\automatic_updates\Validation\StatusChecker $status_checker */
    $status_checker = $this->container->get(StatusChecker::class);
    $this->assertNull($status_checker->getLastRunTime());
    $status_checker->run();
    $last_run_time = $status_checker->getLastRunTime();
    $this->assertIsInt($last_run_time);
    $status_checker->clearStoredResults();
    // The last run time should be unaffected by clearing stored results.
    $this->assertSame($last_run_time, $status_checker->getLastRunTime());
  }

  /**
   * Tests that status checks are not run during site installation.
   */
  public function testNoStatusCheckOnSiteInstall(): void {
    $this->enableModules(['automatic_updates']);

    $GLOBALS['install_state'] = [];
    $this->assertTrue(InstallerKernel::installationAttempted());

    $this->container->get(ModuleInstallerInterface::class)
      ->install(['automatic_updates_test_status_checker']);
    // Ensure that status checks have never been run.
    $this->assertNull($this->container->get(StatusChecker::class)->getLastRunTime());
  }

  /**
   * Tests that status checks are not run during config sync.
   */
  public function testNoStatusCheckOnConfigSync(): void {
    $this->enableModules(['automatic_updates']);

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $this->container->get('config.storage');

    $is_syncing = $this->container->get(ConfigInstallerInterface::class)
      ->setSourceStorage($storage)
      ->setSyncing(TRUE)
      ->isSyncing();
    $this->assertTrue($is_syncing);

    $this->container->get(ModuleInstallerInterface::class)
      ->install(['automatic_updates_test_status_checker']);
    // Ensure that status checks have never been run.
    $this->assertNull($this->container->get(StatusChecker::class)->getLastRunTime());
  }

}
