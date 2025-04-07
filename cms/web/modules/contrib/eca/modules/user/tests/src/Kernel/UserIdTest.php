<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_user_id" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class UserIdTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_user',
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
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
  }

  /**
   * Tests CurrentUserId.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testUserId(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\UserId $condition */
    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '',
      'account' => '',
    ]);
    $this->assertFalse($condition->evaluate(), 'Empty user ID and empty account must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '',
      'account' => '0',
    ]);
    $this->assertFalse($condition->evaluate(), 'Empty user ID and anonymous account ID must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '',
    ]);
    $this->assertFalse($condition->evaluate(), 'User ID 2 and empty account must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '2',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 2 and account 2 must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '3',
      'account' => '3',
    ]);
    $this->assertFalse($condition->evaluate(), 'User ID 3 and account 3 must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '1',
      'account' => 'admin',
    ]);
    $this->assertFalse($condition->evaluate(), 'User ID 1 and account admin must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '0',
      'account' => '[current_user]',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 0 and account [current_user] must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '0',
      'account' => '[current_user:uid]',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 0 and account [current_user:uid] must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '[my_user]',
    ]);
    $this->assertFalse($condition->evaluate(), 'User ID 2 and account [my_user] must evaluate to false.');

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('my_user', User::load(2));

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '[my_user]',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 2 and account [my_user] must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '[my_user:uid]',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 2 and account [my_user:uid] must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '2',
      'account' => '[my_user:id]',
    ]);
    $this->assertTrue($condition->evaluate(), 'User ID 2 and account [my_user:id] must evaluate to true.');

    $token_services->addTokenData('user_uid', '2');
    $condition = $condition_manager->createInstance('eca_user_id', [
      'user_id' => '[user_uid]',
      'account' => '[my_user:uid]',
    ]);
    $this->assertTrue($condition->evaluate(), 'Token with value 2 and account [my_user:uid] must evaluate to true.');

    $token_services->addTokenData('user_uid', '3');
    $this->assertFalse($condition->evaluate(), 'Token with value 3 and account [my_user:uid] must evaluate to false.');
  }

}
