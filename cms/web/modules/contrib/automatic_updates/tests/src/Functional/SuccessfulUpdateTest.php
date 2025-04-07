<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\package_manager\Event\PreApplyEvent;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class SuccessfulUpdateTest extends UpdaterFormTestBase {

  /**
   * Data provider for testSuccessfulUpdate().
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerSuccessfulUpdate(): array {
    return [
      'Modules page, maintenance mode on' => [
        '/admin/modules/update',
        TRUE,
      ],
      'Modules page, maintenance mode off' => [
        '/admin/modules/update',
        FALSE,
      ],
      'Reports page, maintenance mode on' => [
        '/admin/reports/updates/update',
        TRUE,
      ],
      'Reports page, maintenance mode off' => [
        '/admin/reports/updates/update',
        FALSE,
      ],
    ];
  }

  /**
   * Tests an update that has no errors or special conditions.
   *
   * @param string $update_form_url
   *   The URL of the update form to visit.
   * @param bool $maintenance_mode_on
   *   Whether maintenance should be on at the beginning of the update.
   *
   * @dataProvider providerSuccessfulUpdate
   */
  public function testSuccessfulUpdate(string $update_form_url, bool $maintenance_mode_on): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $assert_session = $this->assertSession();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $state = $this->container->get('state');
    $state->set('system.maintenance_mode', $maintenance_mode_on);
    $page = $this->getSession()->getPage();
    $cached_message = $this->setAndAssertCachedMessage();

    $this->drupalGet($update_form_url);
    $assert_session->pageTextNotContains($cached_message->render());
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    $this->assertUpdateReady('9.8.1');
    // Confirm that the site was put into maintenance mode if needed.
    $this->assertMaintenanceMode($maintenance_mode_on);
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $assert_session->addressEquals('/admin/reports/updates');
    $assert_session->pageTextNotContains($cached_message->render());
    // Confirm that the site was in maintenance before the update was applied.
    // @see \Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber::handleEvent()
    $this->assertTrue($state->get(PreApplyEvent::class . '.system.maintenance_mode'));
    $assert_session->pageTextContainsOnce('Update complete!');
    // Confirm the site was returned to the original maintenance mode state.
    $this->assertMaintenanceMode($maintenance_mode_on);
    // Confirm that the apply and post-apply operations happened in
    // separate requests.
    // @see \Drupal\automatic_updates_test\EventSubscriber\RequestTimeRecorder
    $pre_apply_time = $state->get('Drupal\package_manager\Event\PreApplyEvent time');
    $post_apply_time = $state->get('Drupal\package_manager\Event\PostApplyEvent time');
    $this->assertNotEmpty($pre_apply_time);
    $this->assertNotEmpty($post_apply_time);
    $this->assertNotSame($pre_apply_time, $post_apply_time);
  }

  /**
   * Asserts maintenance is the expected value and correct message appears.
   *
   * @param bool $expected_maintenance_mode
   *   Whether maintenance mode is expected to be on or off.
   */
  private function assertMaintenanceMode(bool $expected_maintenance_mode): void {
    $this->assertSame($this->container->get('state')
      ->get('system.maintenance_mode'), $expected_maintenance_mode);
    if ($expected_maintenance_mode) {
      $this->assertSession()
        ->pageTextContains('Operating in maintenance mode.');
    }
    else {
      $this->assertSession()
        ->pageTextNotContains('Operating in maintenance mode.');
    }
  }

}
