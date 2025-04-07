<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\user\Entity\User;
use Drupal\user\Event\UserEvents as CoreUserEvents;
use Drupal\user\Event\UserFloodEvent;

/**
 * Kernel tests for events provided by "eca_user".
 *
 * @group eca
 * @group eca_user
 */
class UserEventsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_user',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
  }

  /**
   * Tests reacting upon events provided by "eca_base".
   */
  public function testUserEvents(): void {
    // This config does the following:
    // 1. It reacts upon all user events.
    // 2. It writes expected token values into a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_user_events',
      'label' => 'ECA user events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'login' => [
          'plugin' => 'user:login',
          'label' => 'User login',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_login', 'condition' => ''],
          ],
        ],
        'logout' => [
          'plugin' => 'user:logout',
          'label' => 'User logout',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_logout', 'condition' => ''],
          ],
        ],
        'cancel' => [
          'plugin' => 'user:cancel',
          'label' => 'User cancel',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_cancel', 'condition' => ''],
          ],
        ],
        'floodblockip' => [
          'plugin' => 'user:floodblockip',
          'label' => 'User floodblockip',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_floodblockip', 'condition' => ''],
          ],
        ],
        'floodblockuser' => [
          'plugin' => 'user:floodblockuser',
          'label' => 'User floodblockuser',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_floodblockuser', 'condition' => ''],
          ],
        ],
        'set_user' => [
          'plugin' => 'user:set_user',
          'label' => 'User set_user',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_set_user', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_login' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'login',
          'configuration' => [
            'key' => 'login',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
        'write_logout' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'logout',
          'configuration' => [
            'key' => 'logout',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
        'write_cancel' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'cancel',
          'configuration' => [
            'key' => 'cancel',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
        'write_floodblockip' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'floodblockip',
          'configuration' => [
            'key' => 'floodblockip',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
        'write_floodblockuser' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'floodblockuser',
          'configuration' => [
            'key' => 'floodblockuser',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
        'write_set_user' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'set_user',
          'configuration' => [
            'key' => 'set_user',
            'value' => '[entity:account-name] + [account:account-name] + [user:account-name]',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    user_login_finalize(User::load(1));
    $this->assertSame('admin + admin + admin', ArrayWrite::$array['login']);

    user_logout();
    $this->assertSame('admin + admin + admin', ArrayWrite::$array['logout']);

    user_cancel([], 2, 'user_cancel_block');
    $this->assertSame('authenticated + authenticated + guest', ArrayWrite::$array['cancel']);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(new UserFloodEvent('ip', 10, 3600, '127.0.0.1'), CoreUserEvents::FLOOD_BLOCKED_IP);
    $this->assertSame('[entity:account-name] + [account:account-name] + guest', ArrayWrite::$array['floodblockip']);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(new UserFloodEvent('user', 10, 3600, 2), CoreUserEvents::FLOOD_BLOCKED_USER);
    $this->assertSame('authenticated + authenticated + guest', ArrayWrite::$array['floodblockuser']);

    \Drupal::currentUser()->setAccount(User::load(1));
    \Drupal::currentUser()->setAccount(User::load(0));
    $this->assertSame('guest + guest + guest', ArrayWrite::$array['set_user']);
  }

}
