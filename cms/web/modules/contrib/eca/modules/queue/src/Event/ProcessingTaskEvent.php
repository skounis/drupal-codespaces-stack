<?php

namespace Drupal\eca_queue\Event;

use Drupal\eca_queue\Task;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatches when a queued ECA task is being processed.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class ProcessingTaskEvent extends Event {

  /**
   * The task that is being processed.
   *
   * @var \Drupal\eca_queue\Task
   */
  protected Task $task;

  /**
   * The ProcessingTaskEvent constructor.
   *
   * @param \Drupal\eca_queue\Task $task
   *   The task that is being processed.
   */
  public function __construct(Task $task) {
    $this->task = $task;
  }

  /**
   * Get the task that is being processed.
   *
   * @return \Drupal\eca_queue\Task
   *   The task that is being processed.
   */
  public function getTask(): Task {
    return $this->task;
  }

}
