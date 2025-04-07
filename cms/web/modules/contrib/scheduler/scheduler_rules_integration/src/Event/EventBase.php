<?php

namespace Drupal\scheduler_rules_integration\Event;

// Drupal\Component\EventDispatcher\Event was introduced in Drupal core 9.1 to
// assist with deprecations and the transition to Symfony 5.
// @todo Remove this when core 9.1 is the lowest supported version.
// @see https://www.drupal.org/project/scheduler/issues/3166688
if (!class_exists('Drupal\Component\EventDispatcher\Event')) {
  class_alias('Symfony\Component\EventDispatcher\Event', 'Drupal\Component\EventDispatcher\Event');
}

use Drupal\Component\EventDispatcher\Event;

/**
 * Base class on which all Scheduler Rules Integration events are extended.
 */
class EventBase extends Event {}
