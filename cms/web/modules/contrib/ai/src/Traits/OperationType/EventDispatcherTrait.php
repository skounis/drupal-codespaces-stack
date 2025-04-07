<?php

namespace Drupal\ai\Traits\OperationType;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Event dispatcher trait for operation types.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait EventDispatcherTrait {

  /**
   * {@inheritdoc}
   */
  public function getEventDispatcher(): EventDispatcherInterface {
    return \Drupal::service('event_dispatcher');
  }

}
