<?php

namespace Drupal\eca_queue\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca_queue\Plugin\QueueWorker\TaskWorker;
use Drupal\eca_queue\Task;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enqueue a Task.
 *
 * @Action(
 *   id = "eca_enqueue_task",
 *   label = @Translation("Enqueue a task"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class EnqueueTask extends ConfigurableActionBase {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueWorkerManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The name of the queue.
   *
   * @var string
   */
  protected static string $queueName = 'eca_task';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setQueueFactory($container->get('queue'));
    $instance->setQueueWorkerManager($container->get('plugin.manager.queue_worker'));
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $task_name = $this->tokenService->replaceClear($this->configuration['task_name']);
    $task_value = $this->tokenService->replaceClear($this->configuration['task_value']);
    $task_not_before = $this->getEarliestProcessingTime();

    $data = [];
    $token_names = trim($this->configuration['tokens'] ?? '');
    if ($token_names !== '') {
      foreach (DataTransferObject::buildArrayFromUserInput($token_names) as $token_name) {
        $token_name = trim($token_name);
        if ($this->tokenService->hasTokenData($token_name)) {
          $data[$token_name] = $this->tokenService->getTokenData($token_name);
        }
      }
    }

    $task = new Task($this->time, $task_name, $task_value, $data, $task_not_before);
    // Check whether the task is to be distributed into its own queue.
    $distributed_queue_name = static::$queueName . ':' . TaskWorker::normalizeTaskName($task_name);
    if ($this->queueWorkerManager->hasDefinition($distributed_queue_name)) {
      $queue_name = $distributed_queue_name;
    }
    else {
      $queue_name = static::$queueName;
    }
    $queue = $this->queueFactory->get($queue_name, TRUE);
    $queue->createQueue();
    if (FALSE === $queue->createItem($task)) {
      throw new \RuntimeException(sprintf("Failed to create queue item for Task '%s' and value '%s' in queue '%s'.", $task->getTaskName(), $task->getTaskValue(), $queue_name));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'task_name' => '',
      'task_value' => '',
      'tokens' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * Set the queue factory.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function setQueueFactory(QueueFactory $queue_factory): void {
    $this->queueFactory = $queue_factory;
  }

  /**
   * Set the queue worker manager.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $manager
   *   The queue worker manager.
   */
  public function setQueueWorkerManager(QueueWorkerManagerInterface $manager): void {
    $this->queueWorkerManager = $manager;
  }

  /**
   * Get the delay in seconds for the task to be created.
   *
   * Can be overwritten by sub-classes, if the support delays.
   *
   * @return int
   *   The delay in seconds for the task to be created.
   */
  protected function getEarliestProcessingTime(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['task_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Task name'),
      '#description' => $this->t('The task name will be used to identify, what type of task is to be processed. When multiple tasks are created that are of the same nature, they should share the same task name. When reacting upon the event "ECA processing queued task", you can use this name to recognize the task.'),
      '#default_value' => $this->configuration['task_name'],
      '#required' => TRUE,
      '#weight' => -50,
    ];
    $form['task_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Task value (optional)'),
      '#description' => $this->t('You may optionally define a task value here for more granular task control.'),
      '#default_value' => $this->configuration['task_value'],
      '#weight' => -40,
    ];
    $form['tokens'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tokens to forward'),
      '#default_value' => $this->configuration['tokens'],
      '#description' => $this->t('Comma separated list of token names from the current context, that will be put into the task.'),
      '#weight' => -30,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['task_name'] = $form_state->getValue('task_name');
    $this->configuration['task_value'] = $form_state->getValue('task_value');
    $this->configuration['tokens'] = $form_state->getValue('tokens');
    parent::submitConfigurationForm($form, $form_state);
  }

}
