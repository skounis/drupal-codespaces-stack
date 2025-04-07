<?php

namespace Drupal\eca_base\Event;

use Cron\CronExpression;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\EcaState;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a cron event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_base\Event
 */
class CronEvent extends Event {

  /**
   * ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected EcaState $state;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * List of timestamps keyed by state ID.
   *
   * @var int[]
   */
  private static array $lastRun = [];

  /**
   * Constructs a new CronEvent object.
   *
   * @param \Drupal\eca\EcaState $state
   *   The ECA state service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(EcaState $state, DateFormatterInterface $dateFormatter, LoggerChannelInterface $logger) {
    $this->state = $state;
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
  }

  /**
   * Determines, if the cron with $id is due for next execution.
   *
   * It receives the last execution time of this event cron and calculates
   * by the given frequency, if the next execution time has already been
   * passed and returns TRUE, if so.
   *
   * @param string $id
   *   The id of the modeller event.
   * @param string $frequency
   *   The frequency as a cron pattern.
   *
   * @return bool
   *   TRUE, if the event $id is due for next execution, FALSE otherwise.
   */
  public function isDue(string $id, string $frequency): bool {
    $currentTime = $this->state->getCurrentTimestamp();
    $key = 'cron-' . $id;
    if (!isset(self::$lastRun[$key])) {
      self::$lastRun[$key] = $this->state->getTimestamp($key);
    }
    $lastRun = self::$lastRun[$key];

    // Cron's maximum granularity is on minute level. Therefore we round the
    // current time to the last passed minute. That way we avoid accidental
    // concurrent runs.
    $currentTime -= ($currentTime % 60);
    $lastRun -= ($lastRun % 60);
    $nextRun = 0;
    $due = FALSE;
    try {
      $nextRun = $this->getNextRunTimestamp($lastRun, $frequency);
      $due = $currentTime >= $nextRun;
    }
    catch (\Exception $e) {
      $this->logger->error('Can not determine next run tim for cron: %msg', [
        '%msg' => $e->getMessage(),
      ]);
    }
    $this->logger->debug('Cron event assertion: now = %current - last = %last - next = %next - due %due', [
      '%current' => $this->dateFormatter->format($currentTime),
      '%last' => $this->dateFormatter->format($lastRun),
      '%next' => $this->dateFormatter->format($nextRun),
      '%due' => $due ? 'yes' : 'no',
    ]);
    return $due;
  }

  /**
   * Calculates the timestamp for the next execution.
   *
   * @param int $lastRunTimestamp
   *   Timestamp, when it was executed last or 0 if it never ran before.
   * @param string $frequency
   *   The frequency as a cron pattern.
   *
   * @return int
   *   Timestamp for next execution.
   *
   * @throws \Exception
   */
  public function getNextRunTimestamp(int $lastRunTimestamp, string $frequency): int {
    $cron = new CronExpression($frequency);
    $dt = new \DateTime();
    $dt
      ->setTimezone(new \DateTimeZone('UTC'))
      ->setTimestamp($lastRunTimestamp);
    return $cron->getNextRunDate($dt)->getTimestamp();
  }

  /**
   * Stores the execution time for the modeller event $id in ECA state.
   *
   * @param string $id
   *   The id of the modeller event.
   * @param int|null $timestamp
   *   (optional) The timestamp value to store. When not given, the current time
   *   will be used.
   */
  public function storeTimestamp(string $id, ?int $timestamp = NULL): void {
    $this->state->setTimestamp('cron-' . $id, $timestamp);
  }

}
