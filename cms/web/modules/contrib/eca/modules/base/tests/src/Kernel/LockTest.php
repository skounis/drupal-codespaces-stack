<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Kernel tests for the lock mechanics of the ECA base module.
 *
 * @group eca
 * @group eca_base
 */
class LockTest extends KernelTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Change container to use database lock backends.
    $container
      ->register('lock', 'Drupal\Core\Lock\DatabaseLockBackend')
      ->addArgument(new Reference('database'));
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $this->installConfig(static::$modules);
  }

  /**
   * Tests TokenSetContext.
   */
  public function testLockAcquire(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_services->addTokenData('admin_user', User::load(1));

    $action = $action_manager->createInstance('eca_lock_acquire', [
      'lock_name' => 'user:[admin_user:uid]',
      'lock_timeout' => '20',
      'lock_wait' => TRUE,
      'token_name' => '',
    ]);
    $this->assertTrue($action->access(NULL));

    $lock = \Drupal::lock();

    $this->assertTrue($lock->acquire('eca:user:1', 0.1));
    $lock->release('eca:user:1');

    $action->execute(NULL);

    // Simulate that the lock service is running under a different PHP process.
    $reset_locks = (function () {
      $this->locks = [];
    })(...);
    $reset_locks->call($lock);

    $this->assertFalse($lock->acquire('eca:user:1', 0.1));

    $action->cleanupAfterSuccessors();

    $this->assertTrue($lock->acquire('eca:user:1', 20.0));

    $action = $action_manager->createInstance('eca_lock_acquire', [
      'lock_name' => 'user:[admin_user:uid]',
      'lock_timeout' => '20',
      'lock_wait' => FALSE,
      'token_name' => 'lock_result',
    ]);

    $reset_locks->call($lock);
    $action->execute(NULL);
    $this->assertSame('0', (string) $token_services->replaceClear('[lock_result]'));

    $lock->release('eca:user:1');
    $action->execute(NULL);
    $this->assertSame('1', (string) $token_services->replaceClear('[lock_result]'));
  }

}
