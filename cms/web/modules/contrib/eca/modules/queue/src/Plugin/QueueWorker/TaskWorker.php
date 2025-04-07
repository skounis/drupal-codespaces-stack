<?php

namespace Drupal\eca_queue\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\RequeueException;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_queue\Event\ProcessingTaskEvent;
use Drupal\eca_queue\Exception\NotYetDueForProcessingException;
use Drupal\eca_queue\QueueEvents;
use Drupal\eca_queue\Task;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Processes enqueued ECA tasks.
 *
 * @QueueWorker(
 *   id = "eca_task",
 *   title = @Translation("ECA Tasks"),
 *   cron = {"time" = 15},
 *   deriver = "Drupal\eca_queue\Plugin\QueueWorker\TaskWorkerDeriver"
 * )
 */
final class TaskWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructs a TaskWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\eca\Token\TokenInterface $token_service
   *   The Token services.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, TokenInterface $token_service, QueueFactory $queue_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    $this->tokenService = $token_service;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): TaskWorker {
    return new TaskWorker(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('eca.token_services'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!($data instanceof Task)) {
      return;
    }
    $task = $data;
    if (!$task->isDueForProcessing()) {
      throw new NotYetDueForProcessingException($task->getDelay(), 'Task is not yet due for processing.');
    }
    try {
      $this->tokenService->addTokenDataProvider($task);
      $this->eventDispatcher->dispatch(new ProcessingTaskEvent($task), QueueEvents::PROCESSING_TASK);
      $this->tokenService->removeTokenDataProvider($task);
    }
    catch (\Exception $e) {
      $queue = $this->queueFactory->get('dummy');
      if ($queue instanceof DelayableQueueInterface) {
        throw new DelayedRequeueException(600, $e->getMessage(), $e->getCode());
      }
      throw new RequeueException($e->getMessage(), $e->getCode());
    }
  }

  /**
   * Normalizes the user-defined task name to be compatible with machine names.
   *
   * @param string $task_name
   *   The task name to normalize.
   *
   * @return string
   *   The normalized task name.
   */
  public static function normalizeTaskName(string $task_name): string {
    return str_replace(' ', '_', mb_strtolower(trim($task_name)));
  }

}
