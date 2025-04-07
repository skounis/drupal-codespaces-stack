<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Behat\Mink\Element\DocumentElement;
use Drupal\automatic_updates\ConsoleUpdateStage;
use Drupal\automatic_updates\UpdateStage;
use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\Event\PostCreateEvent;
use Drupal\package_manager\Event\PostRequireEvent;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\Tests\WebAssert;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tests an end-to-end update of Drupal core.
 *
 * @group automatic_updates
 * @internal
 */
class CoreUpdateTest extends UpdateTestBase {

  /**
   * WebAssert object.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $webAssert;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->webAssert = new WebAssert($this->getMink()->getSession());
  }

  /**
   * {@inheritdoc}
   */
  public function copyCodebase(?\Iterator $iterator = NULL, $working_dir = NULL): void {
    parent::copyCodebase($iterator, $working_dir);

    // Ensure that we will install Drupal 9.8.0 (a fake version that should
    // never exist in real life) initially.
    $this->setUpstreamCoreVersion('9.8.0');
  }

  /**
   * {@inheritdoc}
   */
  public function getCodebaseFinder() {
    // Don't copy .git directories and such, since that just slows things down.
    // We can use ::setUpstreamCoreVersion() to explicitly set the versions of
    // core packages required by the test site.
    return parent::getCodebaseFinder()->ignoreVCS(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(string $template): void {
    parent::createTestProject($template);

    // Prepare an "upstream" version of core, 9.8.1, to which we will update.
    // This version, along with 9.8.0 (which was installed initially), is
    // referenced in our fake release metadata (see
    // fixtures/release-history/drupal.0.0.xml).
    $this->setUpstreamCoreVersion('9.8.1');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);

    // Ensure that Drupal thinks we are running 9.8.0, then refresh information
    // about available updates and ensure that an update to 9.8.1 is available.
    $this->assertCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->visit('/admin/modules/update');
    $this->getMink()->assertSession()->pageTextContains('9.8.1');

    $this->assertStatusReportChecksSuccessful();

    // Ensure that Drupal has write-protected the site directory.
    $this->assertDirectoryIsNotWritable($this->getWebRoot() . '/sites/default');
  }

  /**
   * Tests an end-to-end core update via the API.
   */
  public function testApi(): void {
    $this->createTestProject('RecommendedProject');
    $query = http_build_query([
      'projects' => [
        'drupal' => '9.8.1',
      ],
    ]);
    // Ensure that the update is prevented if the web root and/or vendor
    // directories are not writable.
    $this->assertReadOnlyFileSystemError("/automatic-updates-test-api?$query");

    $mink = $this->getMink();
    $session = $mink->getSession();
    $session->reload();
    $update_status_code = $session->getStatusCode();
    $file_contents = $session->getPage()->getContent();
    $this->assertExpectedStageEventsFired(
      UpdateStage::class,
      [
        // ::assertReadOnlyFileSystemError attempts to start an update
        // multiple times so 'PreCreateEvent' will be fired multiple times.
        // @see \Drupal\Tests\automatic_updates\Build\CoreUpdateTest::assertReadOnlyFileSystemError()
        PreCreateEvent::class,
        PreCreateEvent::class,
        PreCreateEvent::class,
        PostCreateEvent::class,
        PreRequireEvent::class,
        PostRequireEvent::class,
        PreApplyEvent::class,
        PostApplyEvent::class,
      ],
      message: 'Error response: ' . $file_contents
    );
    // Even though the response is what we expect, assert the status code as
    // well, to be extra-certain that there was no kind of server-side error.
    $this->assertSame(200, $update_status_code);

    $this->assertStringContainsString(
      "const VERSION = '9.8.1';",
      file_get_contents($this->getWebRoot() . '/core/lib/Drupal.php')
    );
    $this->assertUpdateSuccessful('9.8.1');

    $this->assertRequestedChangesWereLogged([
      'Update drupal/core-dev from 9.8.0 to 9.8.1',
      'Update drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
    $this->assertAppliedChangesWereLogged([
      'Updated drupal/core from 9.8.0 to 9.8.1',
      'Updated drupal/core-dev from 9.8.0 to 9.8.1',
      'Updated drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
  }

  /**
   * Tests updating during cron using the Automated Cron module.
   */
  public function testAutomatedCron(): void {
    $this->createTestProject('RecommendedProject');
    $this->installModules(['automated_cron']);

    // Reset the record of the last cron run.
    $this->visit('/automatic-updates-test-api/reset-cron');
    $this->getMink()->assertSession()->pageTextContains('cron reset');
    // Make another request so that Automated Cron will be triggered at the end
    // of the request.
    $this->visit('/');
    $this->assertExpectedStageEventsFired(ConsoleUpdateStage::class, wait: 360);
    $this->assertCronUpdateSuccessful();
  }

  /**
   * Tests an end-to-end core update via the UI.
   */
  public function testUi(): void {
    $this->createTestProject('RecommendedProject');

    $mink = $this->getMink();
    $session = $mink->getSession();
    $page = $session->getPage();
    $assert_session = $mink->assertSession();
    $this->coreUpdateTillUpdateReady($page);
    $page->pressButton('Continue');
    $this->waitForBatchJob();
    $assert_session->addressEquals('/admin/reports/updates');
    $assert_session->pageTextContains('Update complete!');
    $assert_session->pageTextContains('Up to date');
    $assert_session->pageTextNotContains('There is a security update available for your version of Drupal.');
    $this->assertExpectedStageEventsFired(UpdateStage::class);
    $this->assertUpdateSuccessful('9.8.1');
    $this->assertRequestedChangesWereLogged([
      'Update drupal/core-dev from 9.8.0 to 9.8.1',
      'Update drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
    $this->assertAppliedChangesWereLogged([
      'Updated drupal/core from 9.8.0 to 9.8.1',
      'Updated drupal/core-dev from 9.8.0 to 9.8.1',
      'Updated drupal/core-recommended from 9.8.0 to 9.8.1',
    ]);
  }

  /**
   * Tests an end-to-end core update via cron.
   *
   * @param string $template
   *   The template project from which to build the test site.
   *
   * @dataProvider providerTemplate
   */
  public function testCron(string $template): void {
    $this->createTestProject($template);

    $this->visit('/admin/reports/status');
    $session = $this->getMink()->getSession();

    $session->getPage()->clickLink('Run cron');
    $this->assertSame(200, $session->getStatusCode());
    $this->assertExpectedStageEventsFired(ConsoleUpdateStage::class, wait: 360);
    $this->assertCronUpdateSuccessful();
  }

  /**
   * Tests stage is destroyed if not available and site is on insecure version.
   */
  public function testStageDestroyedIfNotAvailable(): void {
    $this->createTestProject('RecommendedProject');
    $mink = $this->getMink();
    $session = $mink->getSession();
    $page = $session->getPage();
    $assert_session = $mink->assertSession();
    $this->coreUpdateTillUpdateReady($page);
    $this->visit('/admin/reports/status');
    $assert_session->pageTextContains('Your site is ready for automatic updates.');
    $page->clickLink('Run cron');
    // The stage will first destroy the stage made above before going through
    // stage lifecycle events for the cron update.
    $expected_events = [
      PreCreateEvent::class,
      PostCreateEvent::class,
      PreRequireEvent::class,
      PostRequireEvent::class,
      PreApplyEvent::class,
      PostApplyEvent::class,
    ];
    $this->assertExpectedStageEventsFired(ConsoleUpdateStage::class, $expected_events, 360);
    $this->assertCronUpdateSuccessful();
  }

  /**
   * Asserts that the update is prevented if the filesystem isn't writable.
   *
   * @param string $error_url
   *   A URL where we can see the error message which is raised when parts of
   *   the file system are not writable. This URL will be visited twice: once
   *   for the web root, and once for the vendor directory.
   */
  private function assertReadOnlyFileSystemError(string $error_url): void {
    $directories = [
      'Drupal' => rtrim($this->getWebRoot(), './'),
    ];

    // The location of the vendor directory depends on which project template
    // was used to build the test site, so just ask Composer where it is.
    $directories['vendor'] = $this->runComposer('composer config --absolute vendor-dir', 'project');

    $assert_session = $this->getMink()->assertSession();
    foreach ($directories as $type => $path) {
      chmod($path, 0555);
      $this->assertDirectoryIsNotWritable($path);
      $this->visit($error_url);
      $assert_session->pageTextContains("The $type directory \"$path\" is not writable.");
      chmod($path, 0755);
      $this->assertDirectoryIsWritable($path);
    }
  }

  /**
   * Asserts that a specific version of Drupal core is running.
   *
   * Assumes that a user with permission to view the status report is logged in.
   *
   * @param string $expected_version
   *   The version of core that should be running.
   */
  protected function assertCoreVersion(string $expected_version): void {
    $this->visit('/admin/reports/status');
    $item = $this->getMink()
      ->assertSession()
      ->elementExists('css', 'h3:contains("Drupal Version")')
      ->getParent()
      ->getText();
    $this->assertStringContainsString($expected_version, $item);
  }

  /**
   * Asserts that Drupal core was updated successfully.
   *
   * Assumes that a user with appropriate permissions is logged in.
   *
   * @param string $expected_version
   *   The expected active version of Drupal core.
   */
  private function assertUpdateSuccessful(string $expected_version): void {
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $assert_session = $mink->assertSession();

    // There should be log messages, but no errors or warnings should have been
    // logged by Automatic Updates.
    // The use of the database log here implies one can only retrieve log
    // entries through the dblog UI. This seems non-ideal but it is the choice
    // that requires least custom configuration or custom code. Using the
    // `syslog` or `syslog_test` module  or the `@RestResource=dblog` plugin for
    // the `rest` module require more additional code than the inflexible log
    // querying below.
    $this->visit('/admin/reports/dblog');
    $type_select_field = $assert_session->fieldExists('Type');

    // Automatic Updates may not have logged any entries but if it did there
    // should not have been any errors or warnings.
    if ($type_select_field->find('named', ['option', 'automatic_updates'])) {
      $assert_session->pageTextNotContains('No log messages available.');
      $page->selectFieldOption('Type', 'automatic_updates');
      $page->selectFieldOption('Severity', 'Emergency', TRUE);
      $page->selectFieldOption('Severity', 'Alert', TRUE);
      $page->selectFieldOption('Severity', 'Critical', TRUE);
      $page->selectFieldOption('Severity', 'Warning', TRUE);
      $page->pressButton('Filter');
      $assert_session->pageTextContains('No log messages available.');
    }

    // \Drupal\automatic_updates\Routing\RouteSubscriber::alterRoutes() sets
    // `_automatic_updates_status_messages: skip` on the route for the path
    // `/admin/modules/reports/status`, but not on the `/admin/reports` path. So
    // to test AdminStatusCheckMessages::displayAdminPageMessages(), another
    // page must be visited. `/admin/reports` was chosen, but it could be
    // another too.
    $this->visit('/admin/reports');
    $assert_session->statusCodeEquals(200);
    // @see \Drupal\automatic_updates\Validation\AdminStatusCheckMessages::displayAdminPageMessages()
    $this->webAssert->statusMessageNotExists('error');
    $this->webAssert->statusMessageNotExists('warning');

    $web_root = $this->getWebRoot();
    $placeholder = file_get_contents("$web_root/core/README.txt");
    $this->assertSame("Placeholder for Drupal core $expected_version.", $placeholder);

    foreach (['default.settings.php', 'default.services.yml'] as $file) {
      $file = $web_root . '/sites/default/' . $file;
      $this->assertFileIsReadable($file);
      // The `default.settings.php` and `default.services.yml` files are
      // explicitly excluded from Package Manager operations, since they are not
      // relevant to existing sites. Therefore, ensure that the changes we made
      // to the original (scaffold) versions of the files are not present in
      // the updated site.
      // @see \Drupal\package_manager\PathExcluder\SiteConfigurationExcluder()
      // @see \Drupal\Tests\package_manager\Build\TemplateProjectTestBase::setUpstreamCoreVersion()
      $this->assertStringNotContainsString("# This is part of Drupal $expected_version.", file_get_contents($file));
    }
    $this->assertDirectoryIsNotWritable("$web_root/sites/default");

    $info = $this->runComposer('composer info --self --format json', 'project', TRUE);

    // The production dependencies should have been updated.
    $this->assertSame($expected_version, $info['requires']['drupal/core-recommended']);
    $this->assertSame($expected_version, $info['requires']['drupal/core-composer-scaffold']);
    $this->assertSame($expected_version, $info['requires']['drupal/core-project-message']);
    // The core-vendor-hardening plugin is only used by the legacy project
    // template.
    if ($info['name'] === 'drupal/legacy-project') {
      $this->assertSame($expected_version, $info['requires']['drupal/core-vendor-hardening']);
    }
    // The production dependencies should not be listed as dev dependencies.
    $this->assertArrayNotHasKey('drupal/core-recommended', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-composer-scaffold', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-project-message', $info['devRequires']);
    $this->assertArrayNotHasKey('drupal/core-vendor-hardening', $info['devRequires']);

    // The drupal/core-dev metapackage should not be a production dependency...
    $this->assertArrayNotHasKey('drupal/core-dev', $info['requires']);
    // ...but it should have been updated in the dev dependencies.
    $this->assertSame($expected_version, $info['devRequires']['drupal/core-dev']);
    // The update form should not have any available updates.
    $this->visit('/admin/modules/update');
    $assert_session = $this->getMink()->assertSession();
    $assert_session->pageTextContains('No update available');
    $assert_session->pageTextNotContains('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');

    // The status page should report that we're running the expected version and
    // the README and default site configuration files should contain the
    // placeholder text written by ::setUpstreamCoreVersion(), even though
    // `sites/default` is write-protected.
    // @see ::createTestProject()
    // @see ::setUpstreamCoreVersion()
    $this->assertCoreVersion($expected_version);
  }

  /**
   * Performs core update till update ready form.
   *
   * @param \Behat\Mink\Element\DocumentElement $page
   *   The page element.
   */
  private function coreUpdateTillUpdateReady(DocumentElement $page): void {
    $session = $this->getMink()->getSession();
    $this->visit('/admin/modules');
    $assert_session = $this->getMink()->assertSession($session);
    $assert_session->pageTextContains('There is a security update available for your version of Drupal.');
    $page->clickLink('Update');

    // Ensure that the update is prevented if the web root and/or vendor
    // directories are not writable.
    $this->assertReadOnlyFileSystemError(parse_url($session->getCurrentUrl(), PHP_URL_PATH));
    $session->reload();

    $assert_session->pageTextNotContains('There is a security update available for your version of Drupal.');
    // Ensure test failures provide helpful debug output when failing readiness
    // checks prevent updates.
    // @see \Drupal\Tests\WebAssert::buildStatusMessageSelector()
    if ($error_message = $session->getPage()->find('xpath', '//div[@data-drupal-messages]//div[@aria-label="Error message"]')) {
      /** @var \Behat\Mink\Element\NodeElement $error_message */
      $this->assertSame('', $error_message->getText());
    }
    $page->pressButton('Update to 9.8.1');
    $this->waitForBatchJob();
    $assert_session->pageTextContains('Ready to update');
  }

  /**
   * Assert a cron update ran successfully.
   */
  private function assertCronUpdateSuccessful(): void {
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $this->visit('/admin/reports/dblog');

    // Ensure that the update occurred.
    $page->selectFieldOption('Severity', 'Info');
    $page->pressButton('Filter');
    // There should be a log entry about the successful update.
    $mink->assertSession()
      ->elementAttributeContains('named', ['link', 'Drupal core has been updated from 9.8.0 to 9.8.1'], 'href', '/admin/reports/dblog/event/');
    $this->assertUpdateSuccessful('9.8.1');
  }

  /**
   * Tests updating via the console directly.
   */
  public function testConsoleUpdate(): void {
    $this->createTestProject('RecommendedProject');

    $command = [
      (new PhpExecutableFinder())->find(),
      $this->getWebRoot() . '/core/scripts/auto-update',
      '--verbose',
    ];
    // BEGIN: DELETE FROM CORE MERGE REQUEST
    // Use the `auto-update` command proxy that Composer puts into `vendor/bin`,
    // just to prove that it works.
    $command[1] = 'vendor/bin/auto-update';
    // END: DELETE FROM CORE MERGE REQUEST

    $process = (new Process($command))
      ->setWorkingDirectory($this->getWorkspaceDirectory() . '/project')
      // Give the update process as much time as it needs to run.
      ->setTimeout(NULL);

    $output = $process->mustRun()->getOutput();
    $this->assertStringContainsString('Updating Drupal core to 9.8.1. This may take a while.', $output);
    $this->assertStringContainsString('Running post-apply tasks and final clean-up...', $output);
    $this->assertStringContainsString('Drupal core was successfully updated to 9.8.1!', $output);
    $this->assertStringContainsString('Deleting unused stage directories...', $output);
    $this->assertUpdateSuccessful('9.8.1');
    $this->assertExpectedStageEventsFired(ConsoleUpdateStage::class);

    $pattern = '/^Unused stage directory deleted: (.+)$/m';
    $matches = [];
    preg_match($pattern, $output, $matches);
    $this->assertCount(2, $matches, $output);
    $this->assertDirectoryDoesNotExist($matches[1]);

    // Rerunning the command should exit with a message that no newer version
    // is available.
    $output = $process->mustRun()->getOutput();
    $this->assertStringContainsString("There is no Drupal core update available.", $output);
    // Any defunct stage directories should still be cleaned up (even though
    // there aren't any left).
    $this->assertStringContainsString('Deleting unused stage directories...', $output);
    $this->assertDoesNotMatchRegularExpression($pattern, $output);
  }

}
