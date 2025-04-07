<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class UpdateWarningTest extends UpdaterFormTestBase {

  /**
   * Tests that update can be completed even if a status check throws a warning.
   */
  public function testContinueOnWarning(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');
    $session = $this->getSession();

    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $this->drupalGet('/admin/modules/update');
    $session->getPage()->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateStagedTimes(1);

    $messages = [
      t("The only thing we're allowed to do is to"),
      t("believe that we won't regret the choice"),
      t("we made."),
    ];
    $summary = t('some generic summary');
    $warning = ValidationResult::createWarning($messages, $summary);
    TestSubscriber::setTestResult([$warning], StatusCheckEvent::class);
    $session->reload();

    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Continue');
    $this->assertStatusMessageContainsResult($warning);

    // A warning with only one message should also show its summary.
    $warning = ValidationResult::createWarning([t("I'm still warning you.")], $summary);
    TestSubscriber::setTestResult([$warning], StatusCheckEvent::class);
    $session->reload();
    $this->assertStatusMessageContainsResult($warning);
    $assert_session->buttonExists('Continue');
  }

}
