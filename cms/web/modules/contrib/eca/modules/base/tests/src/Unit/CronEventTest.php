<?php

namespace Drupal\Tests\eca_base\Unit;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\ConfigurableLoggerChannel;
use Drupal\eca\EcaState;
use Drupal\eca_base\Event\CronEvent;
use Drupal\eca_base\Plugin\ECA\Event\BaseEvent;

/**
 * Tests calculation of cron job due dates.
 *
 * @group eca
 * @group eca_base
 */
class CronEventTest extends UnitTestCase {

  /**
   * Tests due times for cron jobs.
   *
   * @param string $last
   *   The current date and time formatted as "Y-M-D h:i".
   * @param string $frequency
   *   The frequency formatted as cron job like e.g. "* * * * *".
   * @param string $expected
   *   The expected date and time formatted as "Y-M-D h:i" for next execution.
   *
   * @dataProvider cronTestDueDatesAndTimesData
   */
  public function testDueDatesAndTimes(string $last, string $frequency, string $expected): void {
    $dt = new \DateTime($last, new \DateTimeZone('UTC'));
    $lastTimestamp = $dt->getTimestamp();
    $dt = new \DateTime($expected, new \DateTimeZone('UTC'));
    $expectedTimestamp = $dt->getTimestamp();

    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $today_0100am = (new \DateTime($now->format('Y-m-d 01:00:01'), new \DateTimeZone('UTC')))->getTimestamp();
    $today_0200am = (new \DateTime($now->format('Y-m-d 02:00:01'), new \DateTimeZone('UTC')))->getTimestamp();
    $state_mock = $this->getStateMock($today_0100am, $today_0200am);
    $date_formatter_mock = $this->createMock(DateFormatter::class);
    $logger_mock = $this->createMock(ConfigurableLoggerChannel::class);

    $event = new CronEvent($state_mock, $date_formatter_mock, $logger_mock);
    $nextTimestamp = $event->getNextRunTimestamp($lastTimestamp, $frequency);
    $this->assertEquals($expectedTimestamp, $nextTimestamp);
  }

  /**
   * Provides test data for ::testDueDatesAndTimes.
   *
   * Each record provides 3 data points:
   * - the last execution date and time
   * - the frequency configuration
   * - the expected date and time for first execution.
   *
   * @return \string[][]
   *   The data records for testing.
   */
  public static function cronTestDueDatesAndTimesData(): array {
    return [
      [
        '2022-06-17 15:30',
        '0 16 * * *',
        '2022-06-17 16:00',
      ],
      [
        '2022-06-17 15:30',
        '0 14 * * *',
        '2022-06-18 14:00',
      ],
      [
        '2022-06-17 15:30',
        '0 * * * *',
        '2022-06-17 16:00',
      ],
    ];
  }

  /**
   * Tests the ::applies method.
   *
   * @param string $frequency
   *   The frequency formatted as cron job like e.g. "* * * * *".
   * @param bool $return_today
   *   The expected return value of the ::applies method when cron runs today.
   * @param bool $return_tomorrow
   *   The expected return value of the ::applies method when cron runs
   *   tomorrow.
   * @param bool $return_after_tomorrow
   *   The expected return value of the ::applies method when cron runs after
   *   tomorrow.
   * @param string $message
   *   A message that describes the data input.
   *
   * @dataProvider appliesData
   */
  public function testApplies(string $frequency, bool $return_today, bool $return_tomorrow, bool $return_after_tomorrow, string $message): void {
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $today_0100am = (new \DateTime($now->format('Y-m-d 01:00:01'), new \DateTimeZone('UTC')))->getTimestamp();
    $today_0200am = (new \DateTime($now->format('Y-m-d 02:00:01'), new \DateTimeZone('UTC')))->getTimestamp();
    $state_mock = $this->getStateMock($today_0100am, $today_0200am);
    $date_formatter_mock = $this->createMock(DateFormatter::class);
    $logger_mock = $this->createMock(ConfigurableLoggerChannel::class);

    $event = new CronEvent($state_mock, $date_formatter_mock, $logger_mock);
    $this->assertSame($return_today, BaseEvent::appliesForWildcard($event, '', $this->randomMachineName() . '::' . $frequency), 'Today - ' . $message);

    // Last run set to today 23:59.
    $last_run = $today_0100am + 86400 - 3660;
    $tomorrow_0200am = $today_0200am + 86400;
    $state_mock = $this->getStateMock($last_run, $tomorrow_0200am);

    $event = new CronEvent($state_mock, $date_formatter_mock, $logger_mock);
    $this->assertSame($return_tomorrow, BaseEvent::appliesForWildcard($event, '', $this->randomMachineName() . '::' . $frequency), 'Tomorrow - ' . $message);

    // Last run set to tomorrow 23:59.
    $last_run = $today_0100am + (2 * 86400) - 3660;
    $tomorrow_0200am += 86400;
    $state_mock = $this->getStateMock($last_run, $tomorrow_0200am);

    $event = new CronEvent($state_mock, $date_formatter_mock, $logger_mock);
    $this->assertSame($return_after_tomorrow, BaseEvent::appliesForWildcard($event, '', $this->randomMachineName() . '::' . $frequency), 'After tomorrow - ' . $message);
  }

  /**
   * Provides test data for ::testApplies.
   *
   * The method ::getStateMock simulates the case that an ECA configuration got
   * created for the first time today at 01:00:01 AM, and that a cron got first
   * executed at 02:00:01 AM. The cron then gets executed at the same clock time
   * on the next day, and again on the followed day (after tomorrow).
   *
   * Each record provides 3 data points:
   * - the frequency configuration
   * - the expected return value of the ::applies method when cron runs today.
   * - the expected return value of the ::applies method when cron runs
   *   tomorrow.
   * - the expected return value of the ::applies method when cron runs after
   *   tomorrow.
   * - A message that describes the data input.
   *
   * @return \string[][]
   *   The data records for testing.
   */
  public static function appliesData(): array {
    $weekday_today = (int) (new \DateTime('now', new \DateTimeZone('UTC')))->format('w');
    $weekday_tomorrow = ($weekday_today + 1) % 7;
    $weekday_after_tomorrow = ($weekday_today + 2) % 7;
    return [
      ['* * * * *', TRUE, TRUE, TRUE, 'Every minute every day.'],
      ['*/2 * * * *', TRUE, TRUE, TRUE, 'Every two minutes every day.'],
      ['30 0 * * *', FALSE, TRUE, TRUE, 'Every day at 00:30 AM.'],
      ['30 1 * * *', TRUE, TRUE, TRUE, 'Every day at 01:30 AM.'],
      ['* * * * ' . $weekday_today, TRUE, FALSE, FALSE, 'Every minute today.'],
      ['0 0 * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE, 'Today at 00:00 AM.'],
      ['1 0 * * ' . $weekday_today, FALSE, FALSE, FALSE, 'Today at 00:01 AM.'],
      ['30 0 * * ' . $weekday_today, FALSE, FALSE, FALSE, 'Today at 00:30 AM.'],
      ['0 1 * * ' . $weekday_today, FALSE, FALSE, FALSE, 'Today at 01:00 AM.'],
      ['30 1 * * ' . $weekday_today, TRUE, FALSE, FALSE, 'Today at 01:30 AM.'],
      ['0,1,2,3,4,5 1 * * ' . $weekday_today, TRUE, FALSE, FALSE,
        'Today at 01:00-05 AM.',
      ],
      ['0 2 * * ' . $weekday_today, TRUE, FALSE, FALSE, 'Today at 02:00 AM.'],
      ['0 3 * * ' . $weekday_today, FALSE, FALSE, FALSE, 'Today at 03:00 AM.'],
      ['* * * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE,
        'Every minute tomorrow.',
      ],
      ['0 0 * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE,
        'Tomorrow at 00:00 AM.',
      ],
      ['0 1 * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE,
        'Tomorrow at 01:00 AM.',
      ],
      ['0 2 * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE,
        'Tomorrow at 02:00 AM.',
      ],
      ['0 3 * * ' . $weekday_tomorrow, FALSE, FALSE, FALSE,
        'Tomorrow at 03:00 AM.',
      ],
      ['0,1,2,3,4,5 1 * * ' . $weekday_tomorrow, FALSE, TRUE, FALSE,
        'Tomorrow at 01:00-05 AM.',
      ],
      ['* * * * ' . $weekday_after_tomorrow, FALSE, FALSE, TRUE,
        'Every minute after tomorrow.',
      ],
      ['0 0 * * ' . $weekday_after_tomorrow, FALSE, FALSE, TRUE,
        'After tomorrow at 00:00 AM.',
      ],
      ['0 1 * * ' . $weekday_after_tomorrow, FALSE, FALSE, TRUE,
        'After tomorrow at 01:00 AM.',
      ],
      ['0 2 * * ' . $weekday_after_tomorrow, FALSE, FALSE, TRUE,
        'After tomorrow at 02:00 AM.',
      ],
      ['0 3 * * ' . $weekday_after_tomorrow, FALSE, FALSE, FALSE,
        'After tomorrow at 03:00 AM.',
      ],
      ['0,1,2,3,4,5 1 * * ' . $weekday_after_tomorrow, FALSE, FALSE, TRUE,
        'After tomorrow at 01:00-05 AM.',
      ],
    ];
  }

  /**
   * Get a mock of the ECA state service.
   *
   * @param int $returnGetTimestamp
   *   The return value of ::getTimestamp.
   * @param int $returnGetCurrentTimestamp
   *   The return value of ::getCurrentTimestamp.
   *
   * @return \Drupal\eca\EcaState
   *   The mock.
   */
  private function getStateMock(int $returnGetTimestamp, $returnGetCurrentTimestamp): EcaState {
    $mock = $this->createMock(EcaState::class);
    $mock->method('getTimestamp')->willReturn($returnGetTimestamp);
    $mock->method('getCurrentTimestamp')->willReturn($returnGetCurrentTimestamp);
    return $mock;
  }

}
