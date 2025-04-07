<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the list operation plugins.
 *
 * @group eca
 * @group eca_base
 */
class ListOperationTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'field',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', 'users_data');
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    $this->installConfig(static::$modules);
  }

  /**
   * Tests the "eca_count" action plugin.
   */
  public function testListCount(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $count = 3;
    $list = (array) $this->randomObject($count);
    $token_services->addTokenData('list', $list);
    /** @var \Drupal\eca_base\Plugin\Action\ListCount $action */
    $action = $action_manager->createInstance('eca_count', [
      'token_name' => 'my_custom_token:value1',
      'list_token' => 'list',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals($count, $token_services->replaceClear('[my_custom_token:value1]'));
    $this->assertEquals('', $token_services->replaceClear('[my_custom_token:value2]'));
  }

  /**
   * Tests the "eca_list_add" action plugin.
   */
  public function testListAdd(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $auth_user = User::load(2);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_base\Plugin\Action\ListAdd $action */
    $action = $action_manager->createInstance('eca_list_add', [
      'list_token' => 'users',
      'method' => 'append',
      'value' => '[auth_user]',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute();
    $this->assertCount(3, $users->getProperties());
    $this->assertSame($auth_user, $users->get(2)->getValue());

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_base\Plugin\Action\ListAdd $action */
    $action = $action_manager->createInstance('eca_list_add', [
      'list_token' => 'users',
      'method' => 'prepend',
      'value' => '[auth_user]',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute();
    $this->assertCount(3, $users->getProperties());
    $this->assertSame($auth_user, $users->get(0)->getValue());
    $this->assertSame(User::load(1)->id(), $users->get(2)->getValue()->id());

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_base\Plugin\Action\ListAdd $action */
    $action = $action_manager->createInstance('eca_list_add', [
      'list_token' => 'users',
      'method' => 'set',
      'index' => '1',
      'value' => '[auth_user]',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertSame($auth_user, $users->get(1)->getValue());
    $this->assertSame(User::load(0)->id(), $users->get(0)->getValue()->id());
  }

  /**
   * Tests the "eca_list_remove" action plugin.
   */
  public function testListRemove(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $auth_user = User::load(2);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'value',
      'token_name' => 'removed_user',
      'value' => '[auth_user]',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertFalse($token_services->hasTokenData('removed_user'));

    $users = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $auth_user = User::load(2);
    $token_services->addTokenData('users', $users);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'value',
      'token_name' => 'removed_user',
      'value' => '[auth_user]',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame($auth_user->id(), $token_services->getTokenData('removed_user')->id());

    $users = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('removed_user', NULL);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'index',
      'token_name' => 'removed_user',
      'index' => '2',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame($auth_user->id(), $token_services->getTokenData('removed_user')->id());

    $users = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('removed_user', NULL);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'index',
      'token_name' => 'removed_user',
      'index' => '1',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame(User::load(1)->id(), $token_services->getTokenData('removed_user')->id());

    $users = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('removed_user', NULL);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'first',
      'token_name' => 'removed_user',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame(User::load(0)->id(), $token_services->getTokenData('removed_user')->id());

    $users = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('removed_user', NULL);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => 'users',
      'method' => 'last',
      'token_name' => 'removed_user',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute();
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame($auth_user->id(), $token_services->getTokenData('removed_user')->id());
  }

  /**
   * Tests the removal of a field value using "eca_list_remove".
   */
  public function testListRemoveFieldValue(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_selection',
      'type' => 'list_string',
      'entity_type' => 'user',
      'settings' => [
        'allowed_values' => [
          'one' => 'One',
          'two' => 'Two',
          'three' => 'Three',
        ],
        'allowed_values_function' => '',
      ],
      'module' => 'options',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_selection',
      'label' => 'Selection',
      'entity_type' => 'user',
      'bundle' => 'user',
      'default_value' => [],
      'field_type' => 'list_string',
    ])->save();

    $user = User::load(2);
    $user->field_selection->setValue(['one', 'two', 'three']);
    $user->save();

    $user = User::load(2);
    $this->assertCount(3, $user->field_selection->getValue());

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('user', $user);
    $action = $action_manager->createInstance('eca_list_remove', [
      'list_token' => '[user:field_selection]',
      'method' => 'value',
      'token_name' => '',
      'value' => 'two',
    ]);
    $action->execute();

    $value = $user->field_selection->getValue();
    $this->assertCount(2, $value);
    $this->assertSame('one', $value[0]['value']);
    $this->assertSame('three', $value[1]['value']);
  }

  /**
   * Tests the "eca_list_save_data" action plugin.
   */
  public function testListSaveData(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $users = DataTransferObject::create([
      User::create(['uid' => 3, 'name' => 'authenticated3']),
      User::create(['uid' => 4, 'name' => 'authenticated4']),
    ]);

    $token_services->addTokenData('users', $users);
    /** @var \Drupal\eca_base\Plugin\Action\ListSaveData $action */
    $action = $action_manager->createInstance('eca_list_save_data', [
      'list_token' => 'users',
    ]);
    $this->assertNull(User::load(3));
    $this->assertNull(User::load(4));
    $this->assertFalse($action->access(NULL), "Anonymous user must not have access to create users.");

    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_base\Plugin\Action\ListSaveData $action */
    $action = $action_manager->createInstance('eca_list_save_data', [
      'list_token' => 'users',
    ]);

    $this->assertTrue($action->access(NULL));

    $action->execute();

    $this->assertNotNull(User::load(3));
    $this->assertNotNull(User::load(4));
  }

  /**
   * Tests the "eca_list_delete_data" action plugin.
   */
  public function testListDeleteData(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $users = DataTransferObject::create([
      User::load(2),
    ]);

    $token_services->addTokenData('users', $users);
    /** @var \Drupal\eca_base\Plugin\Action\ListDeleteData $action */
    $action = $action_manager->createInstance('eca_list_delete_data', [
      'list_token' => 'users',
    ]);
    $this->assertFalse($action->access(NULL), "Anonymous user must not have access to delete users.");
    $this->assertNotNull(User::load(2));

    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_base\Plugin\Action\ListDeleteData $action */
    $action = $action_manager->createInstance('eca_list_delete_data', [
      'list_token' => 'users',
    ]);

    $this->assertTrue($action->access(NULL));

    $action->execute();

    $this->assertNull(User::load(2));
  }

}
