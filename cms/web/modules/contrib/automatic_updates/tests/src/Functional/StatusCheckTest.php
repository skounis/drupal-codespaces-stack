<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\automatic_updates_test_status_checker\EventSubscriber\TestSubscriber2;
use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use Drupal\system\SystemManager;
use Drupal\Tests\automatic_updates\Traits\ValidationTestTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests status checks.
 *
 * @group automatic_updates
 * @internal
 */
class StatusCheckTest extends AutomaticUpdatesFunctionalTestBase {

  use CronRunTrait;
  use ValidationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user who can view the status report.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $reportViewerUser;

  /**
   * A user who can view the status report and run status checks.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $checkerRunnerUser;

  /**
   * The test checker.
   *
   * @var \Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1
   */
  protected $testChecker;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager_test_validation',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $this->mockActiveCoreVersion('9.8.1');

    $this->reportViewerUser = $this->createUser([
      'administer site configuration',
      'access administration pages',
      'administer blocks',
    ]);
    $this->checkerRunnerUser = $this->createUser([
      'administer site configuration',
      'administer software updates',
      'access administration pages',
      'access site in maintenance mode',
      'administer modules',
      'administer blocks',
    ]);
    $this->drupalLogin($this->reportViewerUser);
  }

  /**
   * Tests status checks are displayed after Automatic Updates is installed.
   *
   * @dataProvider providerTestModuleFormInstallDisplay
   */
  public function testModuleFormInstallDisplay(int $results_severity): void {
    // Uninstall Automatic Updates as it is installed in TestBase setup().
    $this->container->get('module_installer')->uninstall(['automatic_updates']);
    $expected_result = $this->createValidationResult($results_severity);
    TestSubscriber::setTestResult([$expected_result], StatusCheckEvent::class);

    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet('admin/modules');
    $page = $this->getSession()->getPage();
    $page->checkField('modules[automatic_updates][enable]');
    $page->pressButton('Install');
    // CORE_MR_ONLY:// Confirm installing the experimental module.
    // CORE_MR_ONLY:$page->pressButton('Continue');
    // Cron Updates will always be disabled on installation as per
    // automatic_updates.settings.yml .
    $session = $this->assertSession();
    $session->pageTextNotContains($expected_result->messages[0]->render());
    $session->linkExists('See status report for more details.');
  }

  /**
   * Provides data for testModuleFormInstallDisplay.
   */
  public static function providerTestModuleFormInstallDisplay(): array {
    return [
      'Error' => [
        SystemManager::REQUIREMENT_ERROR,
      ],
      'Warning' => [
        SystemManager::REQUIREMENT_WARNING,
      ],
    ];
  }

  /**
   * Tests status checks on status report page.
   */
  public function testStatusChecksOnStatusReport(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->container->get('module_installer')->install(['automatic_updates', 'automatic_updates_test']);

    // If the site is ready for updates, the users will see the same output
    // regardless of whether the user has permission to run updates.
    $this->drupalLogin($this->reportViewerUser);
    $this->checkForUpdates();
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors();
    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors(TRUE);

    // Confirm a user without the permission to run status checks does not
    // have a link to run the checks when the checks need to be run again.
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
    $key_value = $this->container->get('keyvalue.expirable')->get('automatic_updates');
    $key_value->delete('status_check_last_run');
    $this->drupalLogin($this->reportViewerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors();
    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors(TRUE);

    // Confirm a user with the permission to run status checks does have a link
    // to run the checks when the checks need to be run again.
    $this->drupalLogin($this->reportViewerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors();
    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors(TRUE);
    /** @var \Drupal\package_manager\ValidationResult[] $expected_results */
    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);

    // Run the status checks.
    $this->clickLink('Rerun readiness checks');
    $assert->statusCodeEquals(200);
    // Confirm redirect back to status report page.
    $assert->addressEquals('/admin/reports/status');
    // Assert that when the runners are run manually the message that updates
    // will not be performed because of errors is displayed on the top of the
    // page in message.
    $assert->pageTextMatchesCount(2, '/' . preg_quote(static::$errorsExplanation) . '/');
    $this->assertErrors($expected_results, TRUE);

    // Confirm a user without permission to run the checks sees the same error.
    $this->drupalLogin($this->reportViewerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertErrors($expected_results);

    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet('/admin/reports/status');

    $expected_results = [
      'error' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
      'warning' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $page->clickLink('Rerun readiness checks');
    // We should see the summaries and messages, even if there's only 1 message.
    $this->assertErrors([$expected_results['error']], TRUE);
    $this->assertWarnings([$expected_results['warning']], TRUE);

    // If there's a result with only one message, but no summary, ensure that
    // message is displayed.
    $result = ValidationResult::createError([t('A lone message, with no summary.')]);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    $page->clickLink('Rerun readiness checks');
    $this->assertErrors([$result], TRUE);

    $expected_results = [
      'error' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR, 2),
      'warning' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING, 2),
    ];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $page->clickLink('Rerun readiness checks');
    // Confirm that both messages and summaries will be displayed when there are
    // multiple messages.
    $this->assertErrors([$expected_results['error']], TRUE);
    $this->assertWarnings([$expected_results['warning']], TRUE);

    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING, 2)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $page->clickLink('Rerun readiness checks');
    $assert->pageTextContainsOnce('Update readiness checks');
    // Confirm that warnings will display on the status report if there are no
    // errors.
    $this->assertWarnings($expected_results, TRUE);

    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $page->clickLink('Rerun readiness checks');
    $assert->pageTextContainsOnce('Update readiness checks');
    $this->assertWarnings($expected_results, TRUE);
  }

  /**
   * Data provider for URLs to the admin page.
   *
   * These particular admin routes are tested as status checks are disabled on
   * certain routes but not on these.
   *
   * @see \Drupal\automatic_updates\Routing\RouteSubscriber::alterRoutes()
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerAdminRoutes(): array {
    return [
      'Structure Page' => ['system.admin_structure'],
      'Update settings Page' => ['update.settings'],
    ];
  }

  /**
   * Tests status check results on admin pages.
   *
   * @param string $admin_route
   *   The admin route to check.
   *
   * @dataProvider providerAdminRoutes
   */
  public function testStatusChecksOnAdminPages(string $admin_route): void {
    $assert = $this->assertSession();

    $this->container->get('module_installer')->install(['automatic_updates', 'automatic_updates_test']);

    // If site is ready for updates no message will be displayed on admin pages.
    $this->drupalLogin($this->reportViewerUser);
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors();
    $this->drupalGet(Url::fromRoute($admin_route));

    $assert->statusMessageNotExists();

    // Confirm a user without the permission to run status checks does not have
    // a link to run the checks when the checks need to be run again.
    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
    $key_value = $this->container->get('keyvalue.expirable')->get('automatic_updates');
    $key_value->delete('status_check_last_run');
    // A user without the permission to run the checkers will not see a message
    // on other pages if the checkers need to be run again.
    $this->drupalGet(Url::fromRoute($admin_route));
    $assert->statusMessageNotExists();

    // Confirm that a user with the correct permission can also run the checkers
    // on another admin page.
    $this->drupalLogin($this->checkerRunnerUser);
    $this->drupalGet(Url::fromRoute($admin_route));
    $assert->statusMessageContains('Your site has not recently run an update readiness check. Rerun readiness checks now.');
    $this->clickLink('Rerun readiness checks now.');
    $assert->addressEquals(Url::fromRoute($admin_route));
    $assert->pageTextContainsOnce((string) $expected_results[0]->summary);

    $expected_results = [
      '1 error' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
      '1 warning' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $this->runStatusChecks();
    $this->drupalGet(Url::fromRoute($admin_route));
    $assert->pageTextContainsOnce(static::$errorsExplanation);
    // Confirm on admin pages that the summary will be displayed.
    $this->assertSame(SystemManager::REQUIREMENT_ERROR, $expected_results['1 error']->severity);
    $assert->pageTextContainsOnce((string) $expected_results['1 error']->summary);
    $assert->pageTextNotContains($expected_results['1 error']->messages[0]->render());
    // Warnings are not displayed on admin pages if there are any errors.
    $this->assertSame(SystemManager::REQUIREMENT_WARNING, $expected_results['1 warning']->severity);
    $assert->pageTextNotContains($expected_results['1 warning']->messages[0]->render());
    $assert->pageTextNotContains($expected_results['1 warning']->summary->render());

    // Confirm the status check event is not dispatched on every admin page
    // load.
    $unexpected_results = [
      '2 errors' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR, 2),
      '2 warnings' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING, 2),
    ];
    TestSubscriber1::setTestResult($unexpected_results, StatusCheckEvent::class);
    $this->drupalGet(Url::fromRoute($admin_route));
    $assert->pageTextNotContains($unexpected_results['2 errors']->summary->render());
    $assert->pageTextContainsOnce((string) $expected_results['1 error']->summary->render());
    $assert->pageTextNotContains($unexpected_results['2 warnings']->summary->render());
    $assert->pageTextNotContains($expected_results['1 warning']->messages[0]->render());

    // Confirm the updated results will be shown when status checks are run
    // again.
    $this->runStatusChecks();
    $expected_results = $unexpected_results;
    $this->drupalGet(Url::fromRoute($admin_route));
    // Confirm on admin pages only the error summary will be displayed if there
    // is more than 1 error.
    $this->assertSame(SystemManager::REQUIREMENT_ERROR, $expected_results['2 errors']->severity);
    $assert->pageTextNotContains($expected_results['2 errors']->messages[0]->render());
    $assert->pageTextNotContains($expected_results['2 errors']->messages[1]->render());
    $assert->pageTextContainsOnce($expected_results['2 errors']->summary->render());
    $assert->pageTextContainsOnce(static::$errorsExplanation);
    // Warnings are not displayed on admin pages if there are any errors.
    $this->assertSame(SystemManager::REQUIREMENT_WARNING, $expected_results['2 warnings']->severity);
    $assert->pageTextNotContains($expected_results['2 warnings']->messages[0]->render());
    $assert->pageTextNotContains($expected_results['2 warnings']->messages[1]->render());
    $assert->pageTextNotContains($expected_results['2 warnings']->summary->render());

    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING, 2)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $this->runStatusChecks();
    $this->drupalGet(Url::fromRoute($admin_route));
    // Confirm that the warnings summary is displayed on admin pages if there
    // are no errors.
    $assert->pageTextNotContains(static::$errorsExplanation);
    $this->assertSame(SystemManager::REQUIREMENT_WARNING, $expected_results[0]->severity);
    $assert->pageTextNotContains($expected_results[0]->messages[0]->render());
    $assert->pageTextNotContains($expected_results[0]->messages[1]->render());
    $assert->pageTextContainsOnce(static::$warningsExplanation);
    $assert->pageTextContainsOnce($expected_results[0]->summary->render());

    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $this->runStatusChecks();
    $this->drupalGet(Url::fromRoute($admin_route));
    $assert->pageTextNotContains(static::$errorsExplanation);
    // Confirm that a single warning is displayed and not the summary on admin
    // pages if there is only 1 warning and there are no errors.
    $this->assertSame(SystemManager::REQUIREMENT_WARNING, $expected_results[0]->severity);
    $assert->pageTextContainsOnce(static::$warningsExplanation);
    $assert->pageTextContainsOnce((string) $expected_results[0]->summary->render());
    $assert->pageTextNotContains($expected_results[0]->messages[0]->render());

    // Confirm status check messages are not displayed when cron updates are
    // disabled.
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::DISABLED)
      ->save();
    $this->drupalGet('admin/structure');
    $this->checkForMetaRefresh();
    $assert->pageTextNotContains(static::$warningsExplanation);
    $assert->pageTextNotContains($expected_results[0]->messages[0]->render());
  }

  /**
   * Tests installing a module with a checker before installing Automatic Updates.
   */
  public function testStatusCheckAfterInstall(): void {
    $assert = $this->assertSession();
    $this->drupalLogin($this->checkerRunnerUser);
    $this->container->get('module_installer')->uninstall(['automatic_updates']);

    $this->drupalGet('admin/reports/status');
    $assert->pageTextNotContains('Update readiness checks');

    // We have to install the automatic_updates_test module because it provides
    // the functionality to retrieve our fake release history metadata.
    $this->container->get('module_installer')->install(['automatic_updates', 'automatic_updates_test']);
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::SECURITY)
      ->save();
    $this->drupalGet('admin/reports/status');
    $this->assertNoErrors(TRUE);

    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber2::setTestResult($expected_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->install(['automatic_updates_test_status_checker']);
    $this->drupalGet('admin/structure');
    $assert->pageTextContainsOnce((string) $expected_results[0]->summary->render());

    // Confirm that installing a module runs the checkers, even if the new
    // module does not provide any validators.
    $previous_results = $expected_results;
    $expected_results = [
      '2 errors' => $this->createValidationResult(SystemManager::REQUIREMENT_ERROR, 2),
      '2 warnings' => $this->createValidationResult(SystemManager::REQUIREMENT_WARNING, 2),
    ];
    TestSubscriber2::setTestResult($expected_results, StatusCheckEvent::class);
    $this->container->get('module_installer')->install(['help']);
    // Check for messages on 'admin/structure' instead of the status report,
    // because validators will be run if needed on the status report.
    $this->drupalGet('admin/structure');
    // Confirm that new checker messages are displayed.
    $assert->pageTextNotContains($previous_results[0]->messages[0]->render());
    $assert->pageTextNotContains($expected_results['2 errors']->messages[0]->render());
    $assert->pageTextContainsOnce($expected_results['2 errors']->summary->render());
  }

  /**
   * Tests that checker message for an uninstalled module is not displayed.
   */
  public function testStatusCheckUninstall(): void {
    $assert = $this->assertSession();
    $this->drupalLogin($this->checkerRunnerUser);

    $expected_results_1 = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($expected_results_1, StatusCheckEvent::class);
    $expected_results_2 = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber2::setTestResult($expected_results_2, StatusCheckEvent::class);
    $this->container->get('module_installer')->install([
      'automatic_updates',
      'automatic_updates_test',
      'automatic_updates_test_status_checker',
    ]);
    // Check for message on 'admin/structure' instead of the status report
    // because checkers will be run if needed on the status report.
    $this->drupalGet('admin/structure');
    $assert->pageTextContainsOnce($expected_results_1[0]->summary->render());
    $assert->pageTextContainsOnce($expected_results_2[0]->summary->render());

    // Confirm that when on of the module is uninstalled the other module's
    // checker result is still displayed.
    $this->container->get('module_installer')->uninstall(['automatic_updates_test_status_checker']);
    $this->drupalGet('admin/structure');
    $assert->pageTextNotContains($expected_results_2[0]->summary->render());
    $assert->pageTextContainsOnce($expected_results_1[0]->summary->render());

    // Confirm that when on of the module is uninstalled the other module's
    // checker result is still displayed.
    $this->container->get('module_installer')->uninstall(['automatic_updates_test']);
    $this->drupalGet('admin/structure');
    $assert->pageTextNotContains($expected_results_2[0]->messages[0]->render());
    $assert->pageTextNotContains($expected_results_1[0]->messages[0]->render());
  }

  /**
   * Tests that stored validation results are deleted after an update.
   */
  public function testStoredResultsClearedAfterUpdate(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalLogin($this->checkerRunnerUser);

    // The current release is 9.8.1 (see ::setUp()), so ensure we're on an older
    // version.
    $this->mockActiveCoreVersion('9.8.0');

    // Flag a validation error, whose summary will be displayed in the messages
    // area.
    $results = [$this->createValidationResult(SystemManager::REQUIREMENT_ERROR)];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $message = $results[0]->summary;

    $this->container->get('module_installer')->install([
      'automatic_updates',
      'automatic_updates_test',
    ]);
    $this->checkForUpdates();
    // The error should be persistently visible, even after the checker stops
    // flagging it.
    $this->drupalGet('/admin/structure');
    $assert_session->pageTextContains($message->render());
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);
    $this->getSession()->reload();
    $assert_session->pageTextContains($message->render());

    // Do the update; we don't expect any errors or special conditions to appear
    // during it. The Update button is displayed because the form does its own
    // status check (without storing the results), and the checker is no
    // longer raising an error.
    $this->drupalGet('/admin/modules/update');
    $assert_session->buttonExists('Update to 9.8.1');
    // Ensure that the previous results are still displayed on another admin
    // page, to confirm that the updater form is not discarding the previous
    // results by doing its checks.
    $this->drupalGet('/admin/structure');
    $assert_session->pageTextContains($message->render());
    // Proceed with the update.
    $this->drupalGet('/admin/modules/update');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.1');
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContains('Update complete!');

    // The warning should not be visible anymore.
    $this->drupalGet('/admin/structure');
    $assert_session->pageTextNotContains($message->render());
  }

  /**
   * Tests that stored results are deleted after certain config changes.
   */
  public function testStoredResultsClearedAfterConfigChanges(): void {
    $this->drupalLogin($this->checkerRunnerUser);

    // Flag a validation error, whose summary will be displayed in the messages
    // area.
    $result = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    $message = $result->summary;

    $this->container->get('module_installer')->install([
      'automatic_updates',
      'automatic_updates_test',
    ]);
    $this->container = $this->container->get('kernel')->getContainer();

    // The error should be persistently visible, even after the checker stops
    // flagging it.
    $this->drupalGet('/admin/structure');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains($message->render());
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);
    $session = $this->getSession();
    $session->reload();
    $assert_session->pageTextContains($message->render());

    $config = $this->config('automatic_updates.settings');
    // If we disable notifications, stored results should not be cleared.
    $config->set('status_check_mail', StatusCheckMailer::DISABLED)->save();
    $session->reload();
    $assert_session->pageTextContains($message->render());

    // If we re-enable them, though, they should be cleared.
    $config->set('status_check_mail', StatusCheckMailer::ERRORS_ONLY)->save();
    $session->reload();
    $assert_session->pageTextNotContains($message->render());
    $no_results_message = 'Your site has not recently run an update readiness check.';
    $assert_session->pageTextContains($no_results_message);

    // If we flag an error again, and keep notifications enabled but change
    // their sensitivity level, the stored results should be cleared.
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    $session->getPage()->clickLink('Rerun readiness checks now');
    $this->drupalGet('/admin/structure');
    $assert_session->pageTextContains($message->render());
    $config->set('status_check_mail', StatusCheckMailer::ALL)->save();
    $session->reload();
    $assert_session->pageTextNotContains($message->render());
    $assert_session->pageTextContains($no_results_message);
  }

  /**
   * Tests that the status report shows cached status check results.
   */
  public function testStatusReportShowsCachedResults(): void {
    $session = $this->getSession();
    $this->drupalLogin($this->checkerRunnerUser);

    $this->container->get('module_installer')->install([
      'automatic_updates',
      'automatic_updates_test',
    ]);
    $this->container = $this->container->get('kernel')->getContainer();

    // Clear stored results that were collected when the module was installed.
    $this->container->get(StatusChecker::class)->clearStoredResults();

    // Flag a validation error, whose summary will be displayed in the messages
    // area.
    $result = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);

    $this->drupalGet('/admin/reports/status');
    $this->assertErrors([$result], TRUE);

    // Clear the result, and ensure that it's still visible because it is
    // cached.
    TestSubscriber::setTestResult(NULL, StatusCheckEvent::class);
    $session->reload();
    $this->assertErrors([$result], TRUE);

    // If unattended updates are configured to run via the command line, we
    // should see a warning that the status checks have not run recently. This
    // is because changing the configuration clears the cached results, since
    // they may be affected by the change.
    // @see \Drupal\automatic_updates\Validation\StatusChecker::onConfigSave()
    $this->config('automatic_updates.settings')
      ->set('unattended.method', 'console')
      ->save();
    $session->reload();
    $assert_session = $this->assertSession();
    $assert_session->pageTextContainsOnce('Unattended updates are configured to run via the console, but do not appear to have run recently.');
    $assert_session->pageTextNotContains((string) $result->messages[0]->render());
  }

  /**
   * Tests the status checks when unattended updates are run via the console.
   */
  public function testUnattendedUpdatesRunFromConsole(): void {
    $this->container->get('module_installer')->install(['automatic_updates']);
    $this->container = $this->container->get('kernel')->getContainer();

    // Clear stored results that were collected when the module was installed.
    $this->container->get(StatusChecker::class)->clearStoredResults();

    $this->config('automatic_updates.settings')
      ->set('unattended.method', 'console')
      ->save();

    // If we visit the status report, we should see an error requirement because
    // unattended updates are configured to run via the terminal, and there are
    // no stored status check results, which means that the console command has
    // probably not run recently (or ever).
    $this->drupalGet('/admin/reports/status');
    $this->assertRequirement('error', 'Unattended updates are configured to run via the console, but do not appear to have run recently.', [], FALSE);

    // We should see a similar message on any other admin page.
    $this->drupalGet('/admin/structure');
    $this->assertSession()
      ->statusMessageContains('Unattended updates are configured to run via the console, but not appear to have run recently.', 'error');
  }

  /**
   * Asserts that the status check requirement displays no errors or warnings.
   *
   * @param bool $run_link
   *   (optional) Whether there should be a link to run the status checks.
   *   Defaults to FALSE.
   */
  private function assertNoErrors(bool $run_link = FALSE): void {
    $this->assertRequirement('checked', 'Your site is ready for automatic updates.', [], $run_link);
  }

  /**
   * Asserts that the displayed status check requirement contains warnings.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The status check results that should be visible.
   * @param bool $run_link
   *   (optional) Whether there should be a link to run the status checks.
   *   Defaults to FALSE.
   */
  private function assertWarnings(array $expected_results, bool $run_link = FALSE): void {
    $this->assertRequirement('warning', static::$warningsExplanation, $expected_results, $run_link);
  }

  /**
   * Asserts that the displayed status check requirement contains errors.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The status check results that should be visible.
   * @param bool $run_link
   *   (optional) Whether there should be a link to run the status checks.
   *   Defaults to FALSE.
   */
  private function assertErrors(array $expected_results, bool $run_link = FALSE): void {
    $this->assertRequirement('error', static::$errorsExplanation, $expected_results, $run_link);
  }

  /**
   * Asserts that the status check requirement is correct.
   *
   * @param string $section
   *   The section of the status report in which the requirement is expected to
   *   be. Can be one of 'error', 'warning', 'checked', or 'ok'.
   * @param string $preamble
   *   The text that should appear before the result messages.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected status check results, in the order we expect them to be
   *   displayed.
   * @param bool $run_link
   *   (optional) Whether there should be a link to run the status checks.
   *   Defaults to FALSE.
   *
   * @see \Drupal\Core\Render\Element\StatusReport::getInfo()
   */
  private function assertRequirement(string $section, string $preamble, array $expected_results, bool $run_link = FALSE): void {
    // Get the meaty part of the requirement element, and ensure that it begins
    // with the preamble, if any.
    $requirement = $this->assertSession()
      ->elementExists('css', "h3#$section ~ details.system-status-report__entry:contains('Update readiness checks') .system-status-report__entry__value");

    if ($preamble) {
      $this->assertStringStartsWith($preamble, $requirement->getText());
    }

    // Convert the expected results into strings.
    $expected_messages = [];
    foreach ($expected_results as $result) {
      $messages = $result->messages;
      $summary = $result->summary;
      if ($summary) {
        $expected_messages[] = $summary;
      }
      $expected_messages = array_merge($expected_messages, $messages);
    }
    $expected_messages = array_map('strval', $expected_messages);

    // The results should appear in the given order.
    $this->assertSame($expected_messages, $this->getMessagesFromRequirement($requirement));
    // Check for the presence or absence of a link to run the checks.
    $this->assertSame($run_link, $requirement->hasLink('Rerun readiness checks'));
  }

  /**
   * Extracts the status check result messages from the requirement element.
   *
   * @param \Behat\Mink\Element\NodeElement $requirement
   *   The page element containing the status check results.
   *
   * @return string[]
   *   The status result messages (including summaries), in the order they
   *   appear on the page.
   */
  private function getMessagesFromRequirement(NodeElement $requirement): array {
    $messages = [];

    // Each list item will either contain a simple string (for results with only
    // one message), or a details element with a series of messages.
    $items = $requirement->findAll('css', 'li');
    foreach ($items as $item) {
      $details = $item->find('css', 'details');

      if ($details) {
        $messages[] = $details->find('css', 'summary')->getText();
        $messages = array_merge($messages, $this->getMessagesFromRequirement($details));
      }
      else {
        $messages[] = $item->getText();
      }
    }
    return array_unique($messages);
  }

  /**
   * Runs status checks.
   */
  private function runStatusChecks(): void {
    $this->drupalGet('/admin/reports/status');
    $this->clickLink('Rerun readiness checks');
  }

}
