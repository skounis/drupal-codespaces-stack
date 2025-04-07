<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_test\Datetime;

use Drupal\Component\Datetime\Time;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test service for altering the request time.
 */
class TestTime extends Time {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $decoratorTime;

  /**
   * Constructs a TestTime object.
   *
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The RequestStack object.
   */
  public function __construct(Time $time, RequestStack $request_stack) {
    $this->decoratorTime = $time;
    parent::__construct($request_stack);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    if ($faked_date = \Drupal::state()->get('automatic_updates_test.fake_date_time')) {
      return \DateTime::createFromFormat('U', $faked_date)->getTimestamp();
    }
    return $this->decoratorTime->getRequestTime();
  }

  /**
   * Sets a fake time from an offset that will be used in the test.
   *
   * @param string $offset
   *   A date/time offset string as used by \DateTime::modify.
   */
  public static function setFakeTimeByOffset(string $offset): void {
    $fake_time = (new \DateTime())->modify($offset)->format('U');
    \Drupal::state()->set('automatic_updates_test.fake_date_time', $fake_time);
  }

}
