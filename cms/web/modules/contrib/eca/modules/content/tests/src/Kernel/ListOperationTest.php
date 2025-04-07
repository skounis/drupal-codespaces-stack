<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the list operation plugins.
 *
 * @group eca
 * @group eca_content
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
    'eca_content',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    $this->installConfig(static::$modules);
  }

  /**
   * Tests the "eca_list_add_entity" action plugin.
   */
  public function testListAddEntity(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $auth_user = User::load(2);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_content\Plugin\Action\ListAddEntity $action */
    $action = $action_manager->createInstance('eca_list_add_entity', [
      'list_token' => 'users',
      'method' => 'append',
      'object' => 'auth_user',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute($auth_user);
    $this->assertCount(3, $users->getProperties());
    $this->assertSame($auth_user, $users->get(2)->getValue());

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_content\Plugin\Action\ListAddEntity $action */
    $action = $action_manager->createInstance('eca_list_add_entity', [
      'list_token' => 'users',
      'method' => 'prepend',
      'object' => 'auth_user',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute($auth_user);
    $this->assertCount(3, $users->getProperties());
    $this->assertSame($auth_user, $users->get(0)->getValue());
    $this->assertSame(User::load(1)->id(), $users->get(2)->getValue()->id());

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_content\Plugin\Action\ListAddEntity $action */
    $action = $action_manager->createInstance('eca_list_add_entity', [
      'list_token' => 'users',
      'method' => 'set',
      'index' => '1',
      'object' => 'auth_user',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute($auth_user);
    $this->assertCount(2, $users->getProperties());
    $this->assertSame($auth_user, $users->get(1)->getValue());
    $this->assertSame(User::load(0)->id(), $users->get(0)->getValue()->id());
  }

  /**
   * Tests the "eca_list_remove_entity" action plugin.
   */
  public function testListRemoveEntity(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $users = DataTransferObject::create([User::load(0), User::load(1)]);
    $auth_user = User::load(2);
    $token_services->addTokenData('users', $users);
    $token_services->addTokenData('auth_user', $auth_user);
    /** @var \Drupal\eca_base\Plugin\Action\ListRemove $action */
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'value',
      'token_name' => 'removed_user',
      'object' => 'auth_user',
    ]);
    $this->assertCount(2, $users->getProperties());
    $action->execute($auth_user);
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
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'value',
      'token_name' => 'removed_user',
      'object' => 'auth_user',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute($auth_user);
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
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'index',
      'token_name' => 'removed_user',
      'index' => '2',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute($auth_user);
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
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'index',
      'token_name' => 'removed_user',
      'index' => '1',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute($auth_user);
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
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'first',
      'token_name' => 'removed_user',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute($auth_user);
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
    $action = $action_manager->createInstance('eca_list_remove_entity', [
      'list_token' => 'users',
      'method' => 'last',
      'token_name' => 'removed_user',
    ]);
    $this->assertCount(3, $users->getProperties());
    $action->execute($auth_user);
    $this->assertCount(2, $users->getProperties());
    $this->assertTrue($token_services->hasTokenData('removed_user'));
    $this->assertSame($auth_user->id(), $token_services->getTokenData('removed_user')->id());
  }

}
