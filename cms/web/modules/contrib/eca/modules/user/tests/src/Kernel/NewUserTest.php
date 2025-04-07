<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_new_user" action plugin.
 *
 * @group eca
 * @group eca_user
 */
class NewUserTest extends KernelTestBase {

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
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
  }

  /**
   * Tests NewUser.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testNewUser(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_user\Plugin\Action\NewUser $action */
    $action = $action_manager->createInstance('eca_new_user', [
      'token_name' => 'my_user1',
      'name' => 'theusername',
      'mail' => 'user1@example.com',
    ]);
    $action->execute();
    $this->assertSame('theusername', $token_services->getTokenData('my_user1:name')->first()->value, 'The user name should be "theusername".');
    $token_services->getTokenData('my_user1')->save();

    // Test the auto-generation of the unique user name.
    $action = $action_manager->createInstance('eca_new_user', [
      'token_name' => 'my_user2',
      'name' => 'theusername',
      'mail' => 'user2@example.com',
    ]);
    $action->execute();
    $this->assertSame('theusername-1', $token_services->getTokenData('my_user2:name')->first()->value, 'The user name should be "theusername-1".');
    $token_services->getTokenData('my_user2')->save();

    // Test the auto-generation of the unique user name again.
    $action = $action_manager->createInstance('eca_new_user', [
      'token_name' => 'my_user3',
      'name' => 'theusername',
      'mail' => 'user3@example.com',
    ]);
    $action->execute();
    $this->assertSame('theusername-2', $token_services->getTokenData('my_user3:name')->first()->value, 'The user name should be "theusername-2".');
    $token_services->getTokenData('my_user3')->save();

    // Test that no user with the same email address gets created.
    $action = $action_manager->createInstance('eca_new_user', [
      'token_name' => 'my_user4',
      'name' => 'theusername',
      'mail' => 'user3@example.com',
    ]);
    try {
      $action->execute();
    }
    catch (\InvalidArgumentException $e) {
      // This should be thrown.
    }
    $this->assertFalse($token_services->hasTokenData('my_user4'), 'The second user with user3@example.com should no have been created.');

    $account_switcher->switchBack();
  }

}
