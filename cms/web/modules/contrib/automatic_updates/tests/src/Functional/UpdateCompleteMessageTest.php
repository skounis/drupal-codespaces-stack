<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager\Event\PostApplyEvent;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class UpdateCompleteMessageTest extends UpdaterFormTestBase {

  /**
   * Data provider for testUpdateCompleteMessage().
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerUpdateCompleteMessage(): array {
    return [
      'maintenance mode off' => [FALSE],
      'maintenance mode on' => [TRUE],
    ];
  }

  /**
   * Tests the update complete message is displayed when another message exist.
   *
   * @param bool $maintenance_mode_on
   *   Whether maintenance should be on at the beginning of the update.
   *
   * @dataProvider providerUpdateCompleteMessage
   */
  public function testUpdateCompleteMessage(bool $maintenance_mode_on): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $assert_session = $this->assertSession();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $state = $this->container->get('state');
    $state->set('system.maintenance_mode', $maintenance_mode_on);
    $page = $this->getSession()->getPage();

    $this->drupalGet('/admin/modules/update');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    // Confirm that the site was put into maintenance mode if needed.
    $custom_message = 'custom status message.';
    TestSubscriber1::setMessage($custom_message, PostApplyEvent::class);
    $page->pressButton('Continue');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContainsOnce($custom_message);
    $assert_session->pageTextContainsOnce('Update complete!');
  }

}
