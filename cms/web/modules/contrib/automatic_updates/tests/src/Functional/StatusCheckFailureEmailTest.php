<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\automatic_updates_test\Datetime\TestTime;
use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\system\SystemManager;
use Drupal\Tests\automatic_updates\Traits\EmailNotificationsTestTrait;
use Drupal\Tests\automatic_updates\Traits\ValidationTestTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests status check failure notification emails during cron runs.
 *
 * @group automatic_updates
 * @covers \Drupal\automatic_updates\StatusCheckMailer
 * @internal
 */
class StatusCheckFailureEmailTest extends AutomaticUpdatesFunctionalTestBase {

  use CronRunTrait;
  use EmailNotificationsTestTrait;
  use ValidationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
    'package_manager_test_validation',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Simulate that we're already fully up to date.
    $this->mockActiveCoreVersion('9.8.1');
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $this->config('automatic_updates.settings')
      ->set('unattended', [
        'level' => CronUpdateRunner::SECURITY,
        'method' => 'console',
      ])
      ->save();
    $this->setUpEmailRecipients();

    // Allow stored available update data to live for as long as possible. By
    // default, the data expires after one day, but this test runs cron many
    // times, with a simulated two hour interval between each run (see
    // ::runCron()). Without this long grace period, all the cron runs in this
    // test would need to run on the same "day", to prevent certain validators
    // from breaking this test due to available update data being irretrievable.
    $this->config('update.settings')
      ->set('check.interval_days', 7)
      ->save();
  }

  /**
   * Asserts that a certain number of failure notifications has been sent.
   *
   * @param int $expected_count
   *   The expected number of failure notifications that should have been sent.
   */
  private function assertSentMessagesCount(int $expected_count): void {
    $sent_messages = $this->getMails([
      'id' => 'automatic_updates_status_check_failed',
    ]);
    $this->assertCount($expected_count, $sent_messages);
  }

  /**
   * Tests that status check failures will trigger emails in some situations.
   */
  public function testFailureNotifications(): void {
    // No messages should have been sent yet.
    $this->assertSentMessagesCount(0);

    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();

    $url = Url::fromRoute('system.status')
      ->setAbsolute()
      ->toString();

    $expected_body = <<<END
Your site has failed some readiness checks for automatic updates and may not be able to receive automatic updates until further action is taken. Visit $url for more information.
END;
    $this->assertMessagesSent('Automatic updates readiness checks failed', $expected_body);

    // Running cron again should not trigger another email (i.e., each
    // recipient has only been emailed once) since the results are unchanged.
    $recipient_count = count($this->emailRecipients);
    $this->assertGreaterThan(0, $recipient_count);
    $sent_messages_count = $recipient_count;
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If a different error is flagged, they should be emailed again.
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag the same error, but a new warning, they should not be emailed
    // again because we ignore warnings by default, and they've already been
    // emailed about this error.
    $results = [
      $error,
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If only a warning is flagged, they should not be emailed again because
    // we ignore warnings by default.
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we stop ignoring warnings, they should be emailed again because we
    // clear the stored results if the relevant configuration is changed.
    $config = $this->config('automatic_updates.settings');
    $config->set('status_check_mail', StatusCheckMailer::ALL)->save();
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag a different warning, they should be emailed again.
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag multiple warnings, they should be emailed again because the
    // number of results has changed, even if the severity hasn't.
    $warnings = [
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
    ];
    TestSubscriber1::setTestResult($warnings, StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we flag an error and a warning, they should be emailed again because
    // the severity has changed, even if the number of results hasn't.
    $results = [
      $this->createValidationResult(SystemManager::REQUIREMENT_WARNING),
      $this->createValidationResult(SystemManager::REQUIREMENT_ERROR),
    ];
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);

    // If we change the order of the results, they should not be emailed again
    // because we are handling the possibility of the results being in a
    // different order.
    $results = array_reverse($results);
    TestSubscriber1::setTestResult($results, StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we disable notifications entirely, they should not be emailed even
    // if a different error is flagged.
    $config->set('status_check_mail', StatusCheckMailer::DISABLED)->save();
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we re-enable notifications and go back to ignoring warnings, they
    // should not be emailed if a new warning is flagged.
    $config->set('status_check_mail', StatusCheckMailer::ERRORS_ONLY)->save();
    $warning = $this->createValidationResult(SystemManager::REQUIREMENT_WARNING);
    TestSubscriber1::setTestResult([$warning], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we disable unattended updates entirely and flag a new error, they
    // should not be emailed.
    $config->set('unattended.level', CronUpdateRunner::DISABLED)->save();
    $error = $this->createValidationResult(SystemManager::REQUIREMENT_ERROR);
    TestSubscriber1::setTestResult([$error], StatusCheckEvent::class);
    $this->runConsoleUpdateCommand();
    $this->assertSentMessagesCount($sent_messages_count);

    // If we re-enable unattended updates, they should be emailed again, even if
    // the results haven't changed.
    $config->set('unattended.level', CronUpdateRunner::SECURITY)->save();
    $this->runConsoleUpdateCommand();
    $sent_messages_count += $recipient_count;
    $this->assertSentMessagesCount($sent_messages_count);
  }

  /**
   * {@inheritdoc}
   */
  protected function runConsoleUpdateCommand(): void {
    static $total_delay = 0;
    // Status checks don't run more than once an hour, so pretend that 61
    // minutes have elapsed since the last run.
    $total_delay += 61;
    TestTime::setFakeTimeByOffset("+$total_delay minutes");

    parent::runConsoleUpdateCommand();

    // Since the terminal command that sent the emails doesn't use the same
    // container as this test, we need to reset the state cache to get
    // information about the sent emails.
    $this->container->get('state')->resetCache();
  }

}
