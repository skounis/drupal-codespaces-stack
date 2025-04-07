<?php

namespace Drupal\scheduler\Exception;

/**
 * Defines an exception when the entity type is not enabled for Scheduler.
 *
 * This exception is thrown when Scheduler attempts to publish or unpublish an
 * entity during cron but the entity type/bundle is not enabled for Scheduler.
 *
 * @see \Drupal\scheduler\SchedulerManager::publish()
 * @see \Drupal\scheduler\SchedulerManager::unpublish()
 */
class SchedulerEntityTypeNotEnabledException extends \Exception {}
