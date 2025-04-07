<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class TableLooksCorrectTest extends UpdaterFormTestBase {

  /**
   * Data provider for testTableLooksCorrect().
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerTableLooksCorrect(): array {
    return [
      'Modules page' => ['modules'],
      'Reports page' => ['reports'],
    ];
  }

  /**
   * Tests that available updates are rendered correctly in a table.
   *
   * @param string $access_page
   *   The page from which the update form should be visited.
   *   Can be one of 'modules' to visit via the module list, or 'reports' to
   *   visit via the administrative reports page.
   *
   * @dataProvider providerTableLooksCorrect
   */
  public function testTableLooksCorrect(string $access_page): void {
    $assert_session = $this->assertSession();

    $assert_minor_update_help = function () use ($assert_session): void {
      $assert_session->pageTextContainsOnce('The following updates are in newer minor version of Drupal. Learn more about updating to another minor version.');
      $assert_session->linkExists('Learn more about updating to another minor version.');
    };
    $assert_no_minor_update_help = function () use ($assert_session): void {
      $assert_session->pageTextNotContains('The following updates are in newer minor version of Drupal. Learn more about updating to another minor version.');
    };

    $page = $this->getSession()->getPage();
    $this->drupalPlaceBlock('local_tasks_block', ['primary' => TRUE]);
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();

    // Navigate to the automatic updates form.
    $this->drupalGet('/admin');
    if ($access_page === 'modules') {
      $this->clickLink('Extend');
      $assert_session->pageTextContainsOnce('There is a security update available for your version of Drupal.');
    }
    else {
      $this->clickLink('Reports');
      $assert_session->pageTextContainsOnce('There is a security update available for your version of Drupal.');
      $this->clickLink('Available updates');
    }
    $this->clickLink('Update');

    // Check the form when there is an update in the installed minor only.
    $assert_session->pageTextContainsOnce('Currently installed: 9.8.0 (Security update required!)');
    $this->checkReleaseTable('#edit-installed-minor', '.update-update-security', '9.8.1', TRUE, 'Latest version of Drupal 9.8 (currently installed):');
    $assert_session->elementNotExists('css', '#edit-next-minor-1');
    $assert_no_minor_update_help();

    // Check the form when there is an update in the next minor only.
    $this->config('automatic_updates.settings')->set('allow_core_minor_updates', TRUE)->save();
    $this->mockActiveCoreVersion('9.7.0');
    $page->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-recommended', '9.8.1', TRUE, 'Latest version of Drupal 9.8 (next minor) (Release notes):');
    $assert_minor_update_help();
    $this->assertReleaseNotesLink(9, 8, '#edit-next-minor-1');
    $assert_session->pageTextContainsOnce('Currently installed: 9.7.0 (Not supported!)');
    $assert_session->elementNotExists('css', '#edit-installed-minor');

    // Check the form when there are updates in the current and next minors but
    // the site does not support minor updates.
    $this->config('automatic_updates.settings')->set('allow_core_minor_updates', FALSE)->save();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml');
    $page->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContainsOnce('Currently installed: 9.7.0 (Update available)');
    $this->checkReleaseTable('#edit-installed-minor', '.update-update-recommended', '9.7.1', TRUE, 'Latest version of Drupal 9.7 (currently installed):');
    $assert_session->elementNotExists('css', '#edit-next-minor-1');
    $assert_no_minor_update_help();

    // Check that if minor updates are enabled the update in the next minor will
    // be visible.
    $this->config('automatic_updates.settings')->set('allow_core_minor_updates', TRUE)->save();
    $this->getSession()->reload();
    $this->checkReleaseTable('#edit-installed-minor', '.update-update-recommended', '9.7.1', TRUE, 'Latest version of Drupal 9.7 (currently installed):');
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-optional', '9.8.2', FALSE, 'Latest version of Drupal 9.8 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 8, '#edit-next-minor-1');
    $assert_minor_update_help();

    $this->mockActiveCoreVersion('9.7.1');
    $page->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContainsOnce('Currently installed: 9.7.1 (Update available)');
    $assert_session->elementNotExists('css', '#edit-installed-minor');
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-recommended', '9.8.2', FALSE, 'Latest version of Drupal 9.8 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 8, '#edit-next-minor-1');
    $assert_minor_update_help();

    // Check that if minor updates are enabled then updates in the next minors
    // are visible.
    $this->config('automatic_updates.settings')->set('allow_core_minor_updates', TRUE)->save();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.10.0.0.xml');
    $this->mockActiveCoreVersion('9.5.0');
    $page->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $assert_session->pageTextNotContains('10.0.0');
    $assert_session->pageTextContainsOnce('Currently installed: 9.5.0 (Update available)');
    $this->checkReleaseTable('#edit-installed-minor', '.update-update-recommended', '9.5.1', TRUE, 'Latest version of Drupal 9.5 (currently installed):');
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-optional', '9.6.1', FALSE, 'Latest version of Drupal 9.6 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 6, '#edit-next-minor-1');
    $this->checkReleaseTable('#edit-next-minor-2', '.update-update-optional', '9.7.2', FALSE, 'Latest version of Drupal 9.7 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 7, '#edit-next-minor-2');
    $assert_minor_update_help();

    // Check that if installed version is unsupported and minor updates are
    // enabled then updates in the next minors are visible.
    $this->mockActiveCoreVersion('9.4.0');
    $page->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $assert_session->pageTextNotContains('10.0.0');
    $assert_session->pageTextContainsOnce('Currently installed: 9.4.0 (Not supported!)');
    $this->checkReleaseTable('#edit-next-minor-1', '.update-update-recommended', '9.5.1', TRUE, 'Latest version of Drupal 9.5 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 5, '#edit-next-minor-1');
    $this->checkReleaseTable('#edit-next-minor-2', '.update-update-recommended', '9.6.1', FALSE, 'Latest version of Drupal 9.6 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 6, '#edit-next-minor-2');
    $this->checkReleaseTable('#edit-next-minor-3', '.update-update-recommended', '9.7.2', FALSE, 'Latest version of Drupal 9.7 (next minor) (Release notes):');
    $this->assertReleaseNotesLink(9, 7, '#edit-next-minor-3');
    $assert_minor_update_help();

    $this->assertUpdateStagedTimes(0);

    // If the minor update help link exists, ensure it links to the right place.
    $help_link = $page->findLink('Learn more about updating to another minor version.');
    if ($help_link) {
      $this->assertStringEndsWith('#minor-update', $help_link->getAttribute('href'));
      $help_link->click();
      $assert_session->statusCodeEquals(200);
      $assert_session->responseContains('id="minor-update"');
    }
  }

  /**
   * Asserts that the release notes link for a given minor version is correct.
   *
   * @param int $major
   *   Major version of next minor release.
   * @param int $minor
   *   Minor version of next minor release.
   * @param string $selector
   *   The selector.
   */
  private function assertReleaseNotesLink(int $major, int $minor, string $selector): void {
    $assert_session = $this->assertSession();
    $row = $assert_session->elementExists('css', $selector);
    $link_href = $assert_session->elementExists('named', ['link', 'Release notes'], $row)->getAttribute('href');
    $this->assertSame('http://example.com/drupal-' . $major . '-' . $minor . '-0-release', $link_href);
  }

}
