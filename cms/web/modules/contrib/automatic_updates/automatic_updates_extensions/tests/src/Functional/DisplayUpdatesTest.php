<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Functional;

/**
 * @covers \Drupal\automatic_updates_extensions\Form\UpdaterForm
 * @group automatic_updates_extensions
 * @internal
 */
final class DisplayUpdatesTest extends UpdaterFormTestBase {

  /**
   * Data provider for testDisplayUpdates().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerDisplayUpdates(): array {
    return [
      'with unrequested updates' => [TRUE],
      'without unrequested updates' => [FALSE],
    ];
  }

  /**
   * Tests the form displays the correct projects which will be updated.
   *
   * @param bool $unrequested_updates
   *   Whether unrequested updates are present during update.
   *
   * @dataProvider providerDisplayUpdates
   */
  public function testDisplayUpdates(bool $unrequested_updates): void {
    $this->container->get('theme_installer')->install(['automatic_updates_theme_with_updates']);
    $this->setReleaseMetadata(__DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml');
    $this->setReleaseMetadata(__DIR__ . "/../../fixtures/release-history/semver_test.1.1.xml");
    $this->setReleaseMetadata(__DIR__ . "/../../../../package_manager/tests/fixtures/release-history/aaa_update_test.1.1.xml");
    $this->setProjectInstalledVersion([
      'semver_test' => '8.1.0',
      'aaa_update_test' => '8.x-2.0',
    ]);
    $this->checkForUpdates();
    $state = $this->container->get('state');
    $page = $this->getSession()->getPage();

    // Navigate to the automatic updates form.
    $this->drupalGet('/admin/reports/updates');
    $this->clickLink('Update Extensions');
    $this->assertTableShowsUpdates(
      'AAA Update test',
      '8.x-2.0',
      '8.x-2.1',
    );
    $this->assertTableShowsUpdates(
      'Semver Test',
      '8.1.0',
      '8.1.1',
      2
    );
    // User will choose both the projects to update and there will be no
    // unrequested updates.
    if ($unrequested_updates === FALSE) {
      $page->checkField('projects[aaa_update_test]');
    }
    $page->checkField('projects[semver_test]');
    $this->getStageFixtureManipulator()
      ->setVersion('drupal/aaa_update_test', '2.1.0')
      ->setVersion('drupal/semver_test_package_name', '8.1.1');
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    $assert_session = $this->assertSession();
    // Both projects will be shown as requested updates if there are no
    // unrequested updates, otherwise one project which user chose will be shown
    // as requested update and other one will be shown as unrequested update.
    if ($unrequested_updates === FALSE) {
      $assert_session->pageTextNotContains('The following dependencies will also be updated:');
    }
    else {
      $assert_session->pageTextContains('The following dependencies will also be updated:');
    }
    $assert_session->pageTextContains('The following projects will be updated:');
    $assert_session->pageTextContains('Semver Test from 8.1.0 to 8.1.1');
    $assert_session->pageTextContains('AAA Update test from 2.0.0 to 2.1.0');
  }

}
