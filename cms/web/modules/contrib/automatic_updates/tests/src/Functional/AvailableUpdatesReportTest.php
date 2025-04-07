<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\Core\Url;

/**
 * Tests changes to the Available Updates report provided by the Update module.
 *
 * @group automatic_updates
 * @internal
 */
class AvailableUpdatesReportTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser([
      'administer site configuration',
      'administer software updates',
      'access administration pages',
      'access site reports',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the Available Updates report links are correct.
   */
  public function testReportLinks(): void {
    $assert = $this->assertSession();
    $form_url = Url::fromRoute('update.report_update')->toString();

    $this->config('automatic_updates.settings')->set('allow_core_minor_updates', TRUE)->save();
    $fixture_directory = __DIR__ . '/../../../package_manager/tests/fixtures/release-history';
    $this->setReleaseMetadata("$fixture_directory/drupal.9.8.1-security.xml");
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $assert->pageTextContains('Security update required! Update now');
    $assert->elementAttributeContains('named', ['link', 'Update now'], 'href', $form_url);
    $this->assertVersionIsListed('9.8.1');

    $this->setReleaseMetadata("$fixture_directory/drupal.9.8.2-older-sec-release.xml");
    $this->mockActiveCoreVersion('9.7.0');
    $this->checkForUpdates();
    $assert->pageTextContains('Security update required! Update now');

    $assert->elementAttributeContains('named', ['link', 'Update now'], 'href', $form_url);
    // Releases that will available on the form should link to the form.
    $this->assertVersionIsListed('9.8.2');
    $this->assertVersionIsListed('9.7.1');
    // Releases that will not be available in the form should link to the
    // project release page.
    $this->assertVersionIsListed('9.8.1');

    $this->setReleaseMetadata("$fixture_directory/drupal.9.8.2.xml");
    $this->checkForUpdates();
    $assert->pageTextContains('Update available Update now');
    $assert->elementAttributeContains('named', ['link', 'Update now'], 'href', $form_url);
    $this->assertVersionIsListed('9.8.2');
  }

  /**
   * Asserts the version download link is correct.
   *
   * @param string $version
   *   The version.
   */
  private function assertVersionIsListed(string $version): void {
    $this->assertSession()->elementExists('css', "table.update .project-update__version:contains(\"$version\")");
  }

}
