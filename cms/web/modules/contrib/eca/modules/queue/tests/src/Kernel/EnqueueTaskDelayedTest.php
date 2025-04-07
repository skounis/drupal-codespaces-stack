<?php

namespace Drupal\Tests\eca_queue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_queue\Plugin\Action\EnqueueTaskDelayed;
use Drupal\eca_queue\Task;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Kernel tests for the "eca_enqueue_task_delayed" action plugin.
 *
 * @group eca
 * @group eca_queue
 */
class EnqueueTaskDelayedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_queue',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests EnqueueTaskDelayed.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testEnqueueTaskDelayed(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $queue = \Drupal::queue('eca_task', TRUE);

    $token_services->addTokenData('entity', User::load(0));
    $token_services->addTokenData('admin', User::load(1));

    // Create an action for enqueuing a task.
    $defaults = [
      'task_name' => 'delayed_task',
      'task_value' => '',
      'tokens' => '',
      'delay_value' => '2',
      'delay_unit' => (string) EnqueueTaskDelayed::DELAY_MINUTES,
    ];
    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [] + $defaults);
    $this->assertTrue($action->access(NULL, User::load(0)), 'Access must be granted.');
    $this->assertTrue($action->access(NULL, User::load(1)), 'Access must be granted.');
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');
    $queue->deleteItem($queue->claimItem());

    // Repeat the same thing.
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [] + $defaults);
    $this->assertTrue($action->access(NULL, User::load(0)), 'Access must be granted.');
    $this->assertTrue($action->access(NULL, User::load(1)), 'Access must be granted.');
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');
    // One more time, without clearing beforehand.
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [] + $defaults);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged before execution.');
    $action->execute();
    $this->assertSame(2, $queue->numberOfItems(), 'Queue must have exactly two items after execution.');
    $queue->deleteItem($queue->claimItem());
    $queue->deleteItem($queue->claimItem());

    // Now create a task, that is to be processed via ECA. The delay of that
    // task it to be set 0 (immediately due). That ECA config will just create
    // another queue item and we assert that this item is then stored in queue.
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [
      'delay_value' => '0',
      'delay_unit' => (string) EnqueueTaskDelayed::DELAY_SECONDS,
    ] + $defaults);
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');

    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'queue_process_delayed',
      'label' => 'ECA queue worker',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_queue' => [
          'plugin' => 'eca_queue:processing_task',
          'label' => 'ECA processing task',
          'configuration' => [
            'task_name' => 'delayed_task',
            'task_value' => '',
          ],
          'successors' => [
            ['id' => 'action_enqueue', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'action_enqueue' => [
          'plugin' => 'eca_enqueue_task_delayed',
          'label' => 'Enqueue another item',
          'configuration' => [
            'task_name' => 'another_task',
            'task_value' => 'delayed_task_value',
            'tokens' => '',
            'delay_value' => '100',
            'delay_unit' => (string) EnqueueTaskDelayed::DELAY_SECONDS,
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    /** @var \Drupal\eca_queue\Plugin\QueueWorker\TaskWorker $queue_worker */
    $queue_worker = $queue_worker_manager->createInstance('eca_task');
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Task item must be a task object.');
    $this->assertTrue($task->isDueForProcessing(), 'Task must be due for processing, because delay is set to 0.');
    $queue_worker->processItem($item->data);
    $queue->deleteItem($item);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item (newly created by ECA configuration).');
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('another_task', $task->getTaskName(), 'Task name must match with the one added via ECA configuration.');
    $this->assertEquals('delayed_task_value', $task->getTaskValue(), 'Task value must match with the one added via ECA configuration.');
    $this->assertFalse($task->hasData('entity'), 'No entity data must have been passed to the task.');
    $this->assertFalse($task->hasData('admin'), 'No user data must have been passed to the task.');

    // When the queue worker works on the newly created item, the ECA config
    // will react upon that again. But it must not execute its action on that
    // event, because it has a different event name. That's why the number of
    // queue items must remain the same.
    $exception = NULL;
    try {
      $queue_worker->processItem($task);
    }
    catch (\Exception $e) {
      $exception = $e;
    }
    finally {
      $this->assertTrue(isset($exception));
      $this->assertEquals('Task is not yet due for processing.', $exception->getMessage());
    }
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged.');
    // Now create another ECA config, that one will react upon the name of
    // the newly created task item, but it is not yet due for processing and
    // therefore the queue must remain unchanged.
    $eca_config_values['id'] .= '_2';
    $eca_config_values['events']['event_queue']['configuration']['task_name'] = 'another_task';
    Eca::create($eca_config_values)->trustData()->save();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged.');
    $exception = NULL;
    try {
      $queue_worker->processItem($task);
    }
    catch (\Exception $e) {
      $exception = $e;
    }
    finally {
      $this->assertTrue(isset($exception));
      $this->assertEquals('Task is not yet due for processing.', $exception->getMessage());
    }
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged.');

    $queue->deleteItem($item);
    while ($item = $queue->claimItem()) {
      $queue->deleteItem($item);
    }
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty.');

    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [
      'task_name' => 'user_task',
      'task_value' => '[entity:account-name]',
      'tokens' => "entity",
      'delay_value' => '100',
      'delay_unit' => (string) EnqueueTaskDelayed::DELAY_SECONDS,
    ] + $defaults);
    $action->execute();
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('user_task', $task->getTaskName(), 'Task name must match with the one defined via plugin configuration.');
    $this->assertEquals('anonymous', $task->getTaskValue(), 'Task value must match with the one defined via plugin configuration.');
    $this->assertTrue($task->hasData('entity'), 'Entity data must have been passed to the task.');
    $this->assertInstanceOf(UserInterface::class, $task->getData('entity'), 'The passed entity Token must be a user.');
    $this->assertSame('0', (string) $task->getData('entity')->id(), 'The loaded entity be the anonymous user.');
    $this->assertFalse($task->hasData('admin'), 'No admin user data must have been passed to the task.');
    $this->assertFalse($task->isDueForProcessing(), 'Task must not be due for processing.');
    $queue->deleteItem($item);

    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task_delayed', [
      'task_name' => 'user_task',
      'task_value' => '[admin:uid]',
      'tokens' => "entity,\nadmin",
      'delay_value' => '0',
      'delay_unit' => (string) EnqueueTaskDelayed::DELAY_SECONDS,
    ] + $defaults);
    $action->execute();
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('user_task', $task->getTaskName(), 'Task name must match with the one defined via plugin configuration.');
    $this->assertEquals('1', $task->getTaskValue(), 'Task value must match with the one defined via plugin configuration.');
    $this->assertTrue($task->hasData('entity'), 'Entity data must have been passed to the task.');
    $this->assertInstanceOf(UserInterface::class, $task->getData('entity'), 'The passed entity Token must be a user.');
    $this->assertSame('0', (string) $task->getData('entity')->id(), 'The loaded entity must be the anonymous user ID.');
    $this->assertTrue($task->hasData('admin'), 'Admin user data must have been passed to the task.');
    $this->assertSame('1', (string) $task->getData('admin')->id(), 'The loaded admin user must be the admin user account, as it was defined in the Token environment.');
    $this->assertTrue($task->isDueForProcessing(), 'Task must be due for processing.');

    $queue->deleteItem($item);
  }

}
