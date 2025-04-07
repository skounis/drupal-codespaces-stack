<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_current_user_permission" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class CurrentUserPermissionTest extends KernelTestBase {

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
    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    user_role_grant_permissions('test_role_eca', ['access content']);
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
  }

  /**
   * Tests CurrentUserPermission.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCurrentUserPermission(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\CurrentUserPermission $condition */
    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'this permission does not exist']);
    $this->assertFalse($condition->evaluate(), 'Non-existent permission must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'administer site configuration']);
    $this->assertFalse($condition->evaluate(), 'Non-assigned permission must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'access content']);
    $this->assertFalse($condition->evaluate(), 'Non-assigned permission must evaluate to false.');

    // Switch to authenticated user.
    $account_switcher->switchTo(User::load(2));

    // Create a plugin for evaluating user permission.
    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'this permission does not exist']);
    $this->assertFalse($condition->evaluate(), 'Non-existent permission must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'administer site configuration']);
    $this->assertFalse($condition->evaluate(), 'Non-assigned permission must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'access content']);
    $this->assertTrue($condition->evaluate(), 'Assigned permission must evaluate to true.');

    // End of tests with authenticated user.
    $account_switcher->switchBack();

    // Now switch to privileged user.
    $account_switcher->switchTo(User::load(1));

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'this permission does not exist']);
    $this->assertTrue($condition->evaluate(), 'Privileged user must always have access.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'administer site configuration']);
    $this->assertTrue($condition->evaluate(), 'Privileged user must always have access.');

    $condition = $condition_manager->createInstance('eca_current_user_permission', ['permission' => 'access content']);
    $this->assertTrue($condition->evaluate(), 'Privileged user must always have access.');

    // End of tests with privileged user.
    $account_switcher->switchBack();
  }

}
