<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Build;

use Drupal\Tests\automatic_updates\Build\UpdateTestBase;
use Drupal\Tests\automatic_updates_extensions\Traits\FormTestTrait;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Tests updating modules in a stage directory.
 *
 * @group automatic_updates_extensions
 * @internal
 */
final class ModuleUpdateTest extends UpdateTestBase {

  use FormTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(string $template): void {
    parent::createTestProject($template);
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml',
      'alpha'  => __DIR__ . '/../../fixtures/release-history/alpha.1.1.0.xml',
      'new_module' => __DIR__ . '/../../fixtures/release-history/new_module.1.1.0.xml',
    ]);

    // Set 'version' and 'project' for the 'alpha' and 'new_module' module to
    // enable the Update to determine the update status.
    $system_info = [
      'alpha' => ['version' => '1.0.0', 'project' => 'alpha'],
      'new_module' => ['version' => '1.0.0', 'project' => 'new_module'],
    ];
    $system_info = var_export($system_info, TRUE);
    $code = <<<END
\$config['update_test.settings']['system_info'] = $system_info;
END;
    $this->writeSettings($code);
    $alpha_repo_path = $this->copyFixtureToTempDirectory(__DIR__ . '/../../../../package_manager/tests/fixtures/build_test_projects/alpha/1.0.0');
    $this->addRepository('alpha', $alpha_repo_path);
    $this->runComposer('composer require drupal/alpha --update-with-all-dependencies', 'project');
    $this->assertModuleVersion('alpha', '1.0.0');
    $fs = new SymfonyFilesystem();
    $fs->mirror($this->copyFixtureToTempDirectory(__DIR__ . '/../../fixtures/new_module'), $this->getWorkspaceDirectory() . '/project/web/modules');
    $this->installModules([
      'automatic_updates_extensions_test_api',
      'alpha',
      'new_module',
    ]);

    // Change the module's upstream version.
    static::copyFixtureFilesTo(__DIR__ . '/../../../../package_manager/tests/fixtures/build_test_projects/alpha/1.1.0', $alpha_repo_path);

    // Ensure that none of the above changes have caused any status check or
    // other errors on the status report.
    $this->checkForUpdates();
    $this->assertStatusReportChecksSuccessful();
  }

  /**
   * Tests updating a module in a stage directory via the API.
   */
  public function testApi(): void {
    $this->createTestProject('RecommendedProject');
    // Use the API endpoint to create a stage and update the 'new_module' module
    // to 1.1.0.
    // @see \Drupal\automatic_updates_extensions_test_api\ApiController::run()
    $query = http_build_query([
      'projects' => [
        'new_module' => '1.1.0',
      ],
    ]);
    $this->visit("/automatic-updates-extensions-test-api?$query");
    $mink = $this->getMink();
    $mink->assertSession()->statusCodeEquals(500);
    $page_text = $mink->getSession()->getPage()->getText();
    $this->assertStringContainsString('The project new_module is not a Drupal project known to Composer and cannot be updated.', $page_text);
    $this->assertStringContainsString('new_module', $page_text);
    // Use the API endpoint to create a stage and update the 'alpha' module to
    // 1.1.0.
    $this->makePackageManagerTestApiRequest(
      '/automatic-updates-extensions-test-api',
      [
        'projects' => [
          'alpha' => '1.1.0',
        ],
      ]
    );

    $updated_composer_json = $this->getWebRoot() . 'modules/contrib/alpha/composer.json';
    // Assert the module was updated.
    $this->assertFileEquals(
      __DIR__ . '/../../../../package_manager/tests/fixtures/build_test_projects/alpha/1.1.0/composer.json',
      $updated_composer_json,
    );
    $this->assertRequestedChangesWereLogged(['Update drupal/alpha from 1.0.0 to 1.1.0']);
    $this->assertAppliedChangesWereLogged(['Updated drupal/alpha from 1.0.0 to 1.1.0']);
  }

  /**
   * Tests updating a module in a stage directory via the UI.
   */
  public function testUi(): void {
    $this->createTestProject('RecommendedProject');

    $mink = $this->getMink();
    $session = $mink->getSession();
    $page = $session->getPage();
    $assert_session = $mink->assertSession();

    $this->visit('/admin/reports/updates');
    // Confirm that 'New Module' project, which is not installed via Composer,
    // has a 1.1.0 update release on the 'Available Updates' page.
    $this->assertReportProjectUpdateVersion('New Module', '1.1.0');
    $page->clickLink('Update Extensions');
    $this->assertUpdateTableRow($assert_session, 'Alpha', '1.0.0', '1.1.0', 1);
    // Confirm that a 'New Module' project does not appear on the form.
    $assert_session->pageTextContains('Other updates were found, but they must be performed manually.');
    $assert_session->fieldNotExists('projects[new_module]');
    // Ensure test failures provide helpful debug output when failing readiness
    // checks prevent updates.
    // @see \Drupal\Tests\WebAssert::buildStatusMessageSelector()
    if ($error_message = $session->getPage()->find('xpath', '//div[@data-drupal-messages]//div[@aria-label="Error message"]')) {
      /** @var \Behat\Mink\Element\NodeElement $error_message */
      $this->assertSame('', $error_message->getText());
    }
    $page->checkField('projects[alpha]');
    $page->pressButton('Update');
    $this->waitForBatchJob();
    $assert_session->pageTextContains('Ready to update');
    $page->checkField('backup');
    $page->pressButton('Continue');
    $this->waitForBatchJob();
    $assert_session->pageTextContains('Update complete!');
    $this->assertModuleVersion('alpha', '1.1.0');
    $this->assertRequestedChangesWereLogged(['Update drupal/alpha from 1.0.0 to 1.1.0']);
    $this->assertAppliedChangesWereLogged(['Updated drupal/alpha from 1.0.0 to 1.1.0']);
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
   * Assert a project version is on the Available Updates page.
   *
   * @param string $project_title
   *   The project title.
   * @param string $expected_version
   *   The expected version.
   */
  protected function assertReportProjectUpdateVersion(string $project_title, string $expected_version): void {
    $mink = $this->getMink();
    $session = $mink->getSession();
    $title_element = $session->getPage()
      ->find('css', ".project-update__title:contains(\"$project_title\")");
    $this->assertNotNull($title_element, "Title element found for $project_title");
    $version_element = $title_element->getParent()->find('css', '.project-update__version-details');
    $this->assertStringContainsString($expected_version, $version_element->getText());
  }

  /**
   * Asserts a module is a specified version.
   *
   * @param string $module_name
   *   The module name.
   * @param string $version
   *   The expected version.
   */
  private function assertModuleVersion(string $module_name, string $version): void {
    $web_root = $this->getWebRoot();
    $composer_json = file_get_contents("$web_root/modules/contrib/$module_name/composer.json");
    $data = json_decode($composer_json, TRUE);
    $this->assertSame($version, $data['version']);
  }

}
