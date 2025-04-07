<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterActionExecutionEvent;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeActionExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca_test_array\Event\ArrayWriteEvent;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the ECA processor engine.
 *
 * @group eca
 * @group eca_core
 */
class ProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests the basic elements of the processor engine.
   */
  public function testProcessorBasics(): void {
    // Restrict access for anonymous users.
    ArrayWrite::$restrictAccess = TRUE;

    // This config does the following:
    // 1. It reacts upon the event when writing into the static array.
    // 2. It then checks with a condition, whether the array has a certain
    //    key-value pair, which was set before.
    // 3. It writes into the array using the same key, but a random value.
    $random_value = uniqid('', TRUE);
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'array_write_process',
      'label' => 'ECA array write process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event that should match up on the first time.',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
          'successors' => [
            ['id' => 'write_array_1', 'condition' => 'array_has_key_value_pair'],
          ],
        ],
      ],
      'conditions' => [
        'array_has_key_value_pair' => [
          'plugin' => 'eca_test_array_has_key_and_value',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
        ],
      ],
      'gateways' => [],
      'actions' => [
        'write_array_1' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            'key' => 'mykey',
            'value' => $random_value,
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $before_execution_event = NULL;
    $num_before_initial = 0;
    $event_dispatcher->addListener(EcaEvents::BEFORE_INITIAL_EXECUTION, function (BeforeInitialExecutionEvent $event) use (&$before_execution_event, &$num_before_initial) {
      $num_before_initial++;
      $before_execution_event = $event->getEvent();
      if (!($before_execution_event instanceof ArrayWriteEvent)) {
        $before_execution_event = NULL;
      }
    });

    $after_execution_event = NULL;
    $num_after_initial = 0;
    $event_dispatcher->addListener(EcaEvents::AFTER_INITIAL_EXECUTION, function (AfterInitialExecutionEvent $event) use (&$after_execution_event, &$num_after_initial) {
      $num_after_initial++;
      $after_execution_event = $event->getEvent();
      if (!($after_execution_event instanceof ArrayWriteEvent)) {
        $after_execution_event = NULL;
      }
    });

    $before_action_event = NULL;
    $before_action_instance = NULL;
    $num_before_action = 0;
    $event_dispatcher->addListener(EcaEvents::BEFORE_ACTION_EXECUTION, function (BeforeActionExecutionEvent $event) use (&$before_action_event, &$num_before_action, &$before_action_instance) {
      $num_before_action++;
      $before_action_event = $event->getEvent();
      if (!($before_action_event instanceof ArrayWriteEvent)) {
        $before_action_event = NULL;
      }
      $before_action_instance = $event->getEcaAction()->getPlugin();
      if (!($before_action_instance instanceof ArrayWrite)) {
        $before_action_instance = NULL;
      }
    });

    $after_action_event = NULL;
    $after_action_instance = NULL;
    $num_after_action = 0;
    $event_dispatcher->addListener(EcaEvents::AFTER_ACTION_EXECUTION, function (AfterActionExecutionEvent $event) use (&$after_action_event, &$num_after_action, &$after_action_instance) {
      $num_after_action++;
      $after_action_event = $event->getEvent();
      if (!($after_action_event instanceof ArrayWriteEvent)) {
        $after_action_event = NULL;
      }
      $after_action_instance = $event->getEcaAction()->getPlugin();
      if (!($after_action_instance instanceof ArrayWrite)) {
        $after_action_instance = NULL;
      }
    });

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    // Executing the action triggers the array write event.
    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals('myvalue', ArrayWrite::$array['mykey'], "The value must remain unchanged, because anonymous user has no access to execute the array write action.");
    $this->assertSame(1, $num_before_initial);
    $this->assertSame(1, $num_after_initial);
    $this->assertSame(1, $num_before_action);
    $this->assertSame(1, $num_after_action);

    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_execution_event);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_action_event);
    $this->assertNotNull($before_action_instance);
    $this->assertNotNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    // Now switch to privileged user.
    $account_switcher->switchTo(User::load(1));

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must have been changed to the random value, because the user has access to execute the action, and the condition must evaluate to be true.");
    $this->assertSame(2, $num_before_initial);
    $this->assertSame(2, $num_after_initial);
    $this->assertSame(2, $num_before_action);
    $this->assertSame(2, $num_after_action);

    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_execution_event);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_action_event);
    $this->assertNotNull($before_action_instance);
    $this->assertNotNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    // Once more, should have the same result.
    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must have been changed to the random value, because the user has access to execute the action, and the condition must evaluate to be true.");
    $this->assertSame(3, $num_before_initial);
    $this->assertSame(3, $num_after_initial);
    $this->assertSame(3, $num_before_action);
    $this->assertSame(3, $num_after_action);

    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_execution_event);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_action_event);
    $this->assertNotNull($before_action_instance);
    $this->assertNotNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    // Change the action configuration to write a different random value.
    $random_value_2 = uniqid('random', TRUE);
    $eca_config = Eca::load('array_write_process');
    $actions = $eca_config->get('actions');
    $actions['write_array_1']['configuration']['value'] = $random_value_2;
    $eca_config->set('actions', $actions);
    $eca_config->trustData()->save();
    \Drupal::entityTypeManager()->getStorage('eca')->resetCache();

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => $random_value,
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must remain unchanged, because the event and condition must not evaluate to be true.");
    $this->assertSame(3, $num_before_initial);
    $this->assertSame(3, $num_after_initial);
    $this->assertSame(3, $num_before_action);
    $this->assertSame(3, $num_after_action);

    $this->assertNull($before_action_event);
    $this->assertNull($after_execution_event);
    $this->assertNull($before_action_event);
    $this->assertNull($after_action_event);
    $this->assertNull($before_action_instance);
    $this->assertNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => $random_value,
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must remain unchanged, because the event and condition must not evaluate to be true.");
    $this->assertSame(3, $num_before_initial);
    $this->assertSame(3, $num_after_initial);
    $this->assertSame(3, $num_before_action);
    $this->assertSame(3, $num_after_action);

    $this->assertNull($before_action_event);
    $this->assertNull($after_execution_event);
    $this->assertNull($before_action_event);
    $this->assertNull($after_action_event);
    $this->assertNull($before_action_instance);
    $this->assertNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    $conditions = $eca_config->get('conditions');
    $conditions['array_has_key_value_pair']['configuration']['value'] = $random_value;
    $eca_config->set('conditions', $conditions);
    $eca_config->trustData()->save();
    \Drupal::entityTypeManager()->getStorage('eca')->resetCache();

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => $random_value,
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must remain unchanged, because the event must not evaluate to be true.");
    $this->assertSame(3, $num_before_initial);
    $this->assertSame(3, $num_after_initial);
    $this->assertSame(3, $num_before_action);
    $this->assertSame(3, $num_after_action);

    $events = $eca_config->get('events');
    $events['array_write']['configuration']['value'] = $random_value;
    $eca_config->set('events', $events);
    $eca_config->trustData()->save();
    \Drupal::entityTypeManager()->getStorage('eca')->resetCache();

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => $random_value,
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value_2, ArrayWrite::$array['mykey'], "The value must have been changed.");
    $this->assertSame(4, $num_before_initial);
    $this->assertSame(4, $num_after_initial);
    $this->assertSame(4, $num_before_action);
    $this->assertSame(4, $num_after_action);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_execution_event);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_action_event);
    $this->assertNotNull($before_action_instance);
    $this->assertNotNull($after_action_instance);
  }

  /**
   * Tests the switch to a specified model user.
   */
  public function testModelUser(): void {
    // Use admin account as model user.
    \Drupal::configFactory()
      ->getEditable('eca.settings')
      ->set('user', '1')
      ->save();

    // Restrict access for anonymous users.
    ArrayWrite::$restrictAccess = TRUE;

    // This config does the following:
    // 1. It reacts upon the event when writing into the static array.
    // 2. It then checks with a condition, whether the array has a certain
    //    key-value pair, which was set before.
    // 3. It writes into the array using the same key, but a random value.
    $random_value = uniqid('', TRUE);
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'array_write_process',
      'label' => 'ECA array write process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event that should match up on the first time.',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
          'successors' => [
            ['id' => 'write_array_1', 'condition' => 'array_has_key_value_pair'],
          ],
        ],
      ],
      'conditions' => [
        'array_has_key_value_pair' => [
          'plugin' => 'eca_test_array_has_key_and_value',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
        ],
      ],
      'gateways' => [],
      'actions' => [
        'write_array_1' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            'key' => 'mykey',
            'value' => $random_value,
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $before_execution_event = NULL;
    $num_before_initial = 0;
    $event_dispatcher->addListener(EcaEvents::BEFORE_INITIAL_EXECUTION, function (BeforeInitialExecutionEvent $event) use (&$before_execution_event, &$num_before_initial) {
      $num_before_initial++;
      $before_execution_event = $event->getEvent();
      if (!($before_execution_event instanceof ArrayWriteEvent)) {
        $before_execution_event = NULL;
      }
    });

    $after_execution_event = NULL;
    $num_after_initial = 0;
    $event_dispatcher->addListener(EcaEvents::AFTER_INITIAL_EXECUTION, function (AfterInitialExecutionEvent $event) use (&$after_execution_event, &$num_after_initial) {
      $num_after_initial++;
      $after_execution_event = $event->getEvent();
      if (!($after_execution_event instanceof ArrayWriteEvent)) {
        $after_execution_event = NULL;
      }
    });

    $before_action_event = NULL;
    $before_action_instance = NULL;
    $num_before_action = 0;
    $event_dispatcher->addListener(EcaEvents::BEFORE_ACTION_EXECUTION, function (BeforeActionExecutionEvent $event) use (&$before_action_event, &$num_before_action, &$before_action_instance) {
      $num_before_action++;
      $before_action_event = $event->getEvent();
      if (!($before_action_event instanceof ArrayWriteEvent)) {
        $before_action_event = NULL;
      }
      $before_action_instance = $event->getEcaAction()->getPlugin();
      if (!($before_action_instance instanceof ArrayWrite)) {
        $before_action_instance = NULL;
      }
    });

    $after_action_event = NULL;
    $after_action_instance = NULL;
    $num_after_action = 0;
    $event_dispatcher->addListener(EcaEvents::AFTER_ACTION_EXECUTION, function (AfterActionExecutionEvent $event) use (&$after_action_event, &$num_after_action, &$after_action_instance) {
      $num_after_action++;
      $after_action_event = $event->getEvent();
      if (!($after_action_event instanceof ArrayWriteEvent)) {
        $after_action_event = NULL;
      }
      $after_action_instance = $event->getEcaAction()->getPlugin();
      if (!($after_action_instance instanceof ArrayWrite)) {
        $after_action_instance = NULL;
      }
    });

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();
    $this->assertTrue(isset(ArrayWrite::$array['mykey']));
    $this->assertEquals($random_value, ArrayWrite::$array['mykey'], "The value must have been changed to the random value, because the user has access to execute the action, and the condition must evaluate to be true.");
    $this->assertSame(1, $num_before_initial);
    $this->assertSame(1, $num_after_initial);
    $this->assertSame(1, $num_before_action);
    $this->assertSame(1, $num_after_action);

    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_execution_event);
    $this->assertNotNull($before_action_event);
    $this->assertNotNull($after_action_event);
    $this->assertNotNull($before_action_instance);
    $this->assertNotNull($after_action_instance);
    $before_execution_event = NULL;
    $after_execution_event = NULL;
    $before_action_event = NULL;
    $after_action_event = NULL;
    $before_action_instance = NULL;
    $after_action_instance = NULL;

    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), "After execution, user must be anonymous.");
  }

}
