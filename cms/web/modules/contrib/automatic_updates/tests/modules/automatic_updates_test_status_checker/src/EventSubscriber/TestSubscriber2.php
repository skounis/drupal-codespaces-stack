<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_test_status_checker\EventSubscriber;

use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\StatusCheckEvent;

/**
 * A test status checker.
 */
class TestSubscriber2 extends TestSubscriber1 {

  protected const STATE_KEY = 'automatic_updates_test_status_checker.checker_results';

  public static function getSubscribedEvents(): array {
    $events[StatusCheckEvent::class][] = ['handleEvent', 4];
    $events[PreCreateEvent::class][] = ['handleEvent', 4];

    return $events;
  }

}
