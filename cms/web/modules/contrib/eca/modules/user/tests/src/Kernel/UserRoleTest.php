<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_user_role" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class UserRoleTest extends KernelTestBase {

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
   * Tests UserRole.
   *
   * It's sufficient to only test one scenario as all other variations
   * get already tested by UserIdTest::testUserId and
   * CurrentUserPermissionTest::testCurrentUserRole.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testUserRole(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\UserRole $condition */
    $condition = $condition_manager->createInstance('eca_user_role', [
      'account' => '0',
      'role' => 'anonymous',
    ]);
    $this->assertTrue($condition->evaluate(), 'Anonymous role must evaluate to true.');
  }

}
