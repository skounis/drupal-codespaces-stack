<?php

namespace Drupal\eca_queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\eca\Token\DataProviderInterface;

/**
 * Task that will be processed in a queue.
 */
class Task implements DataProviderInterface {

  use DependencySerializationTrait;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The name of the task.
   *
   * @var string
   */
  protected string $taskName;

  /**
   * An according value of the task, if any.
   *
   * @var string|null
   */
  protected ?string $taskValue;

  /**
   * According Token data.
   *
   * @var array
   */
  protected array $data;

  /**
   * The timestamp when this task should be processed the earliest.
   *
   * @var int
   */
  protected int $notBefore;

  /**
   * The Task constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param string $task_name
   *   The name of the task.
   * @param string|null $task_value
   *   (optional) An according value of the task.
   * @param array $data
   *   (optional) According Token data.
   * @param int $notBefore
   *   (optional) The timestamp when this task should be processed the earliest.
   */
  public function __construct(TimeInterface $time, string $task_name, ?string $task_value = NULL, array $data = [], int $notBefore = 0) {
    $this->time = $time;
    $this->taskName = $task_name;
    $this->taskValue = $task_value;
    $this->data = $data;
    $this->notBefore = $notBefore;
  }

  /**
   * Get the name of the task.
   *
   * @return string
   *   The task name.
   */
  public function getTaskName(): string {
    return $this->taskName;
  }

  /**
   * Get the according task value.
   *
   * @return string|null
   *   The task value, or NULL if not given.
   */
  public function getTaskValue(): ?string {
    return $this->taskValue ?? NULL;
  }

  /**
   * Determine if the task is due for processing.
   *
   * @return bool
   *   TRUE, if this task is due for processing, FALSE otherwise.
   */
  public function isDueForProcessing(): bool {
    return $this->time->getCurrentTime() >= $this->notBefore;
  }

  /**
   * Get the number of seconds for how long the task should be delayed.
   *
   * @return int
   *   The delay in seconds.
   */
  public function getDelay(): int {
    return $this->notBefore - $this->time->getCurrentTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getData(string $key): mixed {
    return $this->data[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return isset($this->data[$key]);
  }

}
