<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_user_permission" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class UserPermissionTest extends KernelTestBase {

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
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
  }

  /**
   * Tests UserPermission.
   *
   * It's sufficient to only test one scenario as all other variations
   * get already tested by UserIdTest::testUserId and
   * CurrentUserPermissionTest::testCurrentUserPermission.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testUserPermission(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\UserPermission $condition */
    $condition = $condition_manager->createInstance('eca_user_permission', [
      'account' => '0',
      'permission' => 'this permission does not exist',
      'negate' => TRUE,
    ]);
    $this->assertTrue($condition->evaluate(), 'Non-existent permission negated must evaluate to true.');
  }

}
