<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_token_load_user_current" action plugin.
 *
 * @group eca
 * @group eca_user
 */
class LoadCurrentUserTest extends KernelTestBase {

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
   * Tests LoadCurrentUser.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testLoadCurrentUser(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $account_switcher->switchTo(User::load(2));

    /** @var \Drupal\eca_user\Plugin\Action\LoadCurrentUser $action */
    $action = $action_manager->createInstance('eca_token_load_user_current', ['token_name' => 'my_user']);
    $action->execute();
    $this->assertSame($token_services->getTokenData('my_user:uid')->first()->value, \Drupal::currentUser()->id(), 'The id of token object my_user and current user id should be the same.');

    $action = $action_manager->createInstance('eca_token_load_user_current', []);
    $action->execute();
    $this->assertSame($token_services->getTokenData('user:uid')->first()->value, \Drupal::currentUser()->id(), 'The id of token object user and current user id should be the same.');

    $account_switcher->switchBack();
  }

}
