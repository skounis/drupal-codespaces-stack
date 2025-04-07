<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests for the "eca.token_data.current_user" service.
 *
 * @group eca
 * @group eca_user
 */
class CurrentUserDataProviderTest extends KernelTestBase {

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
   * Tests CurrentUserDataProvider.
   */
  public function testCurrentUserDataProvider(): void {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $current_user = \Drupal::currentUser();

    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('user'));
    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('current_user'));
    $this->assertNull($token_services->getTokenData('another_user'));

    $account_switcher->switchTo(User::load(1));
    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('user'));
    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('current_user'));
    $this->assertNull($token_services->getTokenData('another_user'));
    $this->assertNull($token_services->getTokenData('admin'));
    $this->assertSame((string) $current_user->id(), (string) $token_services->getTokenData('user')->id());
    $this->assertSame((string) $current_user->id(), (string) $token_services->getTokenData('current_user')->id());
    $this->assertEquals('admin', $token_services->replace('[user:account-name]'));
    $this->assertEquals('admin', $token_services->replace('[current_user:account-name]'));
    $this->assertEquals('[another_user:account-name]', $token_services->replace('[another_user:account-name]'));

    $account_switcher->switchTo(User::load(2));
    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('user'));
    $this->assertInstanceOf(UserInterface::class, $token_services->getTokenData('current_user'));
    $this->assertNull($token_services->getTokenData('another_user'));
    $this->assertNull($token_services->getTokenData('admin'));
    $this->assertNull($token_services->getTokenData('authenticated'));
    $this->assertSame((string) $current_user->id(), (string) $token_services->getTokenData('user')->id());
    $this->assertSame((string) $current_user->id(), (string) $token_services->getTokenData('current_user')->id());
    $this->assertEquals('authenticated', $token_services->replace('[user:account-name]'));
    $this->assertEquals('authenticated!', $token_services->replace('[current_user:account-name]!'));
    $this->assertEquals('[another_user:account-name]', $token_services->replace('[another_user:account-name]'));
  }

}
