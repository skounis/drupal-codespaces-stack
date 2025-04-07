<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager_test_validation\StagedDatabaseUpdateValidator;
use Drupal\system\SystemManager;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class StagedDatabaseUpdateTest extends UpdaterFormTestBase {

  /**
   * Data provider for testStagedDatabaseUpdates().
   *
   * @return bool[][]
   *   The test cases.
   */
  public static function providerStagedDatabaseUpdates(): array {
    return [
      'maintenance mode on' => [TRUE],
      'maintenance mode off' => [FALSE],
    ];
  }

  /**
   * Tests the update form when staged modules have database updates.
   *
   * @param bool $maintenance_mode_on
   *   Whether the site should be in maintenance mode at the beginning of the
   *   update process.
   *
   * @dataProvider providerStagedDatabaseUpdates
   */
  public function testStagedDatabaseUpdates(bool $maintenance_mode_on): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->container->get('theme_installer')
      ->install(['automatic_updates_theme_with_updates']);
    $cached_message = $this->setAndAssertCachedMessage();

    $state = $this->container->get('state');
    $state->set('system.maintenance_mode', $maintenance_mode_on);

    // Flag a warning, which will not block the update but should be displayed
    // on the updater form.
    $expected_results = [$this->createValidationResult(SystemManager::REQUIREMENT_WARNING)];
    TestSubscriber1::setTestResult($expected_results, StatusCheckEvent::class);
    $messages = reset($expected_results)->messages;

    StagedDatabaseUpdateValidator::setExtensionsWithUpdates([
      'system',
      'automatic_updates_theme_with_updates',
    ]);

    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/update');
    // The warning should be visible.
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains((reset($messages))->render());
    $assert_session->pageTextNotContains($cached_message->render());
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);
    $this->assertUpdateReady('9.8.1');
    // Simulate a staged database update in the automatic_updates_test module.
    // We must do this after the update has started, because the pending updates
    // validator will prevent an update from starting.
    $state->set('automatic_updates_test.new_update', TRUE);
    // The warning from the updater form should be repeated, and we should see
    // a warning about pending database updates. Once the staged changes have
    // been applied, we should be redirected to update.php, where neither
    // warning should be visible.
    $assert_session->pageTextContains((reset($messages))->render());

    // Ensure that a list of pending database updates is visible, along with a
    // short explanation, in the warning messages.
    $possible_update_message = 'Database updates have been detected in the following extensions.<ul><li>System</li><li>Automatic Updates Theme With Updates</li></ul>';
    $warning_messages = $assert_session->elementExists('css', 'div[data-drupal-messages] div[aria-label="Warning message"]');
    $this->assertStringContainsString($possible_update_message, $warning_messages->getHtml());
    if ($maintenance_mode_on === TRUE) {
      $assert_session->fieldNotExists('maintenance_mode');
    }
    else {
      $assert_session->checkboxChecked('maintenance_mode');
    }
    $assert_session->pageTextNotContains($cached_message->render());
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    // Confirm that the site was in maintenance before the update was applied.
    // @see \Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber::handleEvent()
    $this->assertTrue($state->get(PreApplyEvent::class . '.system.maintenance_mode'));
    // Confirm the site remains in maintenance more when redirected to
    // update.php.
    $this->assertTrue($state->get('system.maintenance_mode'));
    $assert_session->pageTextContainsOnce('An error has occurred.');
    $assert_session->pageTextContainsOnce('Continue to the error page');
    $page->clickLink('the error page');
    $assert_session->pageTextContains('Some modules have database schema updates to install. You should run the database update script immediately.');
    $assert_session->linkExists('database update script');
    $assert_session->linkByHrefExists('/update.php');
    $page->clickLink('database update script');
    $assert_session->addressEquals('/update.php');
    $assert_session->pageTextNotContains($cached_message->render());
    $assert_session->pageTextNotContains((reset($messages))->render());
    $assert_session->pageTextNotContains($possible_update_message);
    $this->assertTrue($state->get('system.maintenance_mode'));
    $page->clickLink('Continue');
    // @see automatic_updates_update_1191934()
    $assert_session->pageTextContains('Dynamic automatic_updates_update_1191934');
    $page->clickLink('Apply pending updates');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContains('Updates were attempted.');
    // Confirm the site was returned to the original maintenance module state.
    $this->assertSame($state->get('system.maintenance_mode'), $maintenance_mode_on);
    $assert_session->pageTextNotContains($cached_message->render());
  }

}
