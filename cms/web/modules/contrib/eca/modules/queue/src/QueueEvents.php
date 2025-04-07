<?php

namespace Drupal\eca_queue;

/**
 * Defines events provided by the ECA Queue module.
 */
final class QueueEvents {

  /**
   * Dispatched when a queued ECA task is being processed.
   *
   * @Event
   *
   * @var string
   */
  const PROCESSING_TASK = 'eca_queue.processing_task';

}
