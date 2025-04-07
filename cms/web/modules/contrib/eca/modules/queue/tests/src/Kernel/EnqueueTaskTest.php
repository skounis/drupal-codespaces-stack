<?php

namespace Drupal\Tests\eca_queue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_queue\Task;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_enqueue_task" action plugin.
 *
 * @group eca
 * @group eca_queue
 */
class EnqueueTaskTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_queue',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests EnqueueTask.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testEnqueueTask(): void {
    // Create the Article content type with revisioning and translation enabled.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $queue = \Drupal::queue('eca_task', TRUE);

    // Create a node having two revisions.
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'title' => '123',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();
    $first_vid = $node->getRevisionId();
    $node->setTitle('456');
    $node->setNewRevision();
    $node->save();
    $second_vid = $node->getRevisionId();
    $first_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($first_vid);
    $token_services->addTokenData('entity', $first_revision);
    $token_services->addTokenData('node', $node);

    // Create an action for enqueuing a task.
    $defaults = [
      'task_name' => '',
      'task_value' => '',
      'tokens' => '',
    ];
    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'my_task',
    ] + $defaults);
    $this->assertTrue($action->access(NULL, User::load(0)), 'Access must be granted.');
    $this->assertTrue($action->access(NULL, User::load(1)), 'Access must be granted.');
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');
    $queue->deleteItem($queue->claimItem());

    // Repeat the same thing.
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'my_task',
    ] + $defaults);
    $this->assertTrue($action->access(NULL, User::load(0)), 'Access must be granted.');
    $this->assertTrue($action->access(NULL, User::load(1)), 'Access must be granted.');
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');
    // One more time, without clearing beforehand.
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'my_task',
    ] + $defaults);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged before execution.');
    $action->execute();
    $this->assertSame(2, $queue->numberOfItems(), 'Queue must have exactly two items after execution.');
    $queue->deleteItem($queue->claimItem());
    $queue->deleteItem($queue->claimItem());

    // Now create a task, that is to be processed via ECA. That ECA config
    // will just create another queue item and we assert that this item is then
    // stored in the queue.
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'my_task',
    ] + $defaults);
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty before execution.');
    $action->execute();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution.');

    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'queue_process',
      'label' => 'ECA queue worker',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_queue' => [
          'plugin' => 'eca_queue:processing_task',
          'label' => 'ECA processing task',
          'configuration' => [
            'task_name' => 'my_task',
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
          'plugin' => 'eca_enqueue_task',
          'label' => 'Enqueue another item',
          'configuration' => [
            'task_name' => 'another_task',
            'task_value' => 'my_task_value',
            'tokens' => '',
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
    $queue_worker->processItem($item->data);
    $queue->deleteItem($item);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item after execution (newly created item).');
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('another_task', $task->getTaskName(), 'Task name must match with the one added via ECA configuration.');
    $this->assertEquals('my_task_value', $task->getTaskValue(), 'Task value must match with the one added via ECA configuration.');
    $this->assertFalse($task->hasData('entity'), 'No entity data must have been passed to the task.');
    $this->assertFalse($task->hasData('node'), 'No node data must have been passed to the task.');

    // When the queue worker works on the newly created item, the ECA config
    // will react upon that again. But it must not execute its action on that
    // event, because it has a different event name. That's why the number of
    // queue items must remain the same.
    $queue_worker->processItem($task);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged.');
    // Now create another ECA config, that one will react upon the name of
    // the newly created task item and therefore create another item.
    $eca_config_values['id'] .= '_2';
    $eca_config_values['events']['event_queue']['configuration']['task_name'] = 'another_task';
    Eca::create($eca_config_values)->trustData()->save();
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must be unchanged.');
    $queue_worker->processItem($task);
    $this->assertSame(2, $queue->numberOfItems(), 'Queue must now have one more item.');
    // Create another ECA config, that one will react upon the name of
    // the newly created task item too and therefore create another item.
    // It will additionally use the task value for further restriction. As we
    // now have two configs that react upon the same event, two items will be
    // created.
    $eca_config_values['id'] .= '_3';
    $eca_config_values['events']['event_queue']['configuration']['task_name'] = 'another_task';
    $eca_config_values['events']['event_queue']['configuration']['task_value'] = 'my_task_value';
    Eca::create($eca_config_values)->trustData()->save();
    $queue_worker->processItem($task);
    $this->assertSame(4, $queue->numberOfItems(), 'Queue must now have two more items, because two configurations create one item each.');
    // Create another ECA config, that one will react upon the name of
    // the newly created task item too. The difference is not the task_value,
    // which differs from the queue item's task value. Therefore it should do
    // nothing, but the other two will create two further items.
    $eca_config_values['id'] .= '_4';
    $eca_config_values['events']['event_queue']['configuration']['task_name'] = 'another_task';
    $eca_config_values['events']['event_queue']['configuration']['task_value'] = 'my_task_value__nonexistent';
    Eca::create($eca_config_values)->trustData()->save();
    $queue_worker->processItem($task);
    $this->assertSame(6, $queue->numberOfItems(), 'Queue must now have two more items, because two configurations react upon that.');

    $queue->deleteItem($item);
    while ($item = $queue->claimItem()) {
      $queue->deleteItem($item);
    }
    $this->assertSame(0, $queue->numberOfItems(), 'Queue must be empty.');

    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'node_task',
      'task_value' => '[node:nid]',
      'tokens' => "entity",
    ] + $defaults);
    $action->execute();
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('node_task', $task->getTaskName(), 'Task name must match with the one defined via plugin configuration.');
    $this->assertEquals((string) $node->id(), $task->getTaskValue(), 'Task value must match with the one defined via plugin configuration.');
    $this->assertTrue($task->hasData('entity'), 'Entity data must have been passed to the task.');
    $this->assertInstanceOf(NodeInterface::class, $task->getData('entity'), 'The passed entity Token must be a node.');
    $this->assertSame($first_vid, $task->getData('entity')->getRevisionId(), 'The loaded entity must be the first revision of the node, as it was defined in the Token environment.');
    $this->assertFalse($task->hasData('node'), 'No node data must have been passed to the task.');
    $queue->deleteItem($item);

    /** @var \Drupal\eca_queue\Plugin\Action\EnqueueTask $action */
    $action = $action_manager->createInstance('eca_enqueue_task', [
      'task_name' => 'node_task',
      'task_value' => '[node:nid]',
      'tokens' => "entity,\nnode",
    ] + $defaults);
    $action->execute();
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('node_task', $task->getTaskName(), 'Task name must match with the one defined via plugin configuration.');
    $this->assertEquals((string) $node->id(), $task->getTaskValue(), 'Task value must match with the one defined via plugin configuration.');
    $this->assertTrue($task->hasData('entity'), 'Entity data must have been passed to the task.');
    $this->assertInstanceOf(NodeInterface::class, $task->getData('entity'), 'The passed entity Token must be a node.');
    $this->assertSame($first_vid, $task->getData('entity')->getRevisionId(), 'The loaded entity must be the first revision of the node, as it was defined in the Token environment.');
    $this->assertTrue($task->hasData('node'), 'Node data must have been passed to the task.');
    $this->assertSame($second_vid, $task->getData('node')->getRevisionId(), 'The loaded node must be the second revision, as it was defined in the Token environment.');

    // Create an ECA config that reacts upon the node_task event, using a
    // specific node ID. Clearing the Token data explicitly, so that its Token
    // must catch a value from the task.
    $token_services->clearTokenData();
    $eca_config_values['id'] .= '_5';
    $eca_config_values['events']['event_queue']['configuration']['task_name'] = 'node_task';
    $eca_config_values['events']['event_queue']['configuration']['task_value'] = (string) $node->id();
    $eca_config_values['actions']['action_enqueue']['configuration']['task_name'] = 'node_task_follow';
    $eca_config_values['actions']['action_enqueue']['configuration']['task_value'] = '[node:nid]';
    Eca::create($eca_config_values)->trustData()->save();
    $queue_worker->processItem($task);
    $queue->deleteItem($item);
    $this->assertSame(1, $queue->numberOfItems(), 'Queue must have exactly one item, created by the last ECA config.');
    $item = $queue->claimItem();
    /** @var \Drupal\eca_queue\Task $task */
    $task = $item->data;
    $this->assertInstanceOf(Task::class, $task, 'Newly created queue item must be a Task object.');
    $this->assertEquals('node_task_follow', $task->getTaskName(), 'Task name must match with the one defined via plugin configuration.');
    $this->assertEquals((string) $node->id(), $task->getTaskValue(), 'Task value must match with the one defined via plugin configuration.');
    $this->assertFalse($task->hasData('entity'), 'No entity data must have been passed to the task.');
    $this->assertFalse($task->hasData('node'), 'No node data must have been passed to the task.');

    $queue->deleteItem($item);
  }

  /**
   * Tests distributed task handling.
   */
  public function testTaskDistribution(): void {
    // This ECA configuration puts a task into its own queue.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'queue_process',
      'label' => 'ECA distributed task',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'eca_test_array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event for enqueuing a distributed task.',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
          'successors' => [
            ['id' => 'action_enqueue_distributed', 'condition' => ''],
          ],
        ],
        'event_queue' => [
          'plugin' => 'eca_queue:processing_task',
          'label' => 'ECA processing task',
          'configuration' => [
            'task_name' => 'my_distributed_task',
            'task_value' => '',
            'distribute' => TRUE,
            'cron' => '',
          ],
          'successors' => [
            ['id' => 'action_enqueue_another', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'action_enqueue_distributed' => [
          'plugin' => 'eca_enqueue_task',
          'label' => 'Enqueue a distributed task',
          'configuration' => [
            'task_name' => 'my_distributed_task',
            'task_value' => 'my_task_value',
            'tokens' => '',
          ],
          'successors' => [],
        ],
        'action_enqueue_another' => [
          'plugin' => 'eca_enqueue_task',
          'label' => 'Enqueue another task (not distributed)',
          'configuration' => [
            'task_name' => 'another_task',
            'task_value' => 'my_task_value',
            'tokens' => '',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    $not_distributed_queue = \Drupal::queue('eca_task', TRUE);
    $distributed_queue = \Drupal::queue('eca_task:my_distributed_task', TRUE);
    $this->assertSame(0, $not_distributed_queue->numberOfItems());
    $this->assertSame(0, $distributed_queue->numberOfItems());

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();

    $this->assertSame(1, $distributed_queue->numberOfItems());
    $this->assertSame(0, $not_distributed_queue->numberOfItems());

    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    /** @var \Drupal\eca_queue\Plugin\QueueWorker\TaskWorker $queue_worker */
    $queue_worker = $queue_worker_manager->createInstance('eca_task:my_distributed_task');
    $item = $distributed_queue->claimItem();
    $queue_worker->processItem($item->data);
    $distributed_queue->deleteItem($item);
    $this->assertSame(0, $distributed_queue->numberOfItems());
    $this->assertSame(1, $not_distributed_queue->numberOfItems());
    $not_distributed_queue->deleteQueue();
  }

}
