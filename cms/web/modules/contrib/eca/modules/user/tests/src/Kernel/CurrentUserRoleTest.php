<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_current_user_role" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class CurrentUserRoleTest extends KernelTestBase {

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
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
  }

  /**
   * Tests CurrentUserRole.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCurrentUserRole(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\CurrentUserRole $condition */
    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'this role does not exist']);
    $this->assertFalse($condition->evaluate(), 'Non-existent role must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => '']);
    $this->assertFalse($condition->evaluate(), 'Empty role must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'test_role_eca']);
    $this->assertFalse($condition->evaluate(), 'Test role must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'authenticated']);
    $this->assertFalse($condition->evaluate(), 'Authenticated role must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'anonymous']);
    $this->assertTrue($condition->evaluate(), 'Anonymous role must evaluate to true.');

    // Switch to authenticated user.
    $account_switcher->switchTo(User::load(2));

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'this role does not exist']);
    $this->assertFalse($condition->evaluate(), 'Non-existent role must still evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => '']);
    $this->assertFalse($condition->evaluate(), 'Empty role must still evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'test_role_eca']);
    $this->assertTrue($condition->evaluate(), 'Test role must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'authenticated']);
    $this->assertTrue($condition->evaluate(), 'Authenticated role must evaluate to true.');

    $condition = $condition_manager->createInstance('eca_current_user_role', ['role' => 'anonymous']);
    $this->assertFalse($condition->evaluate(), 'Anonymous role must evaluate to false.');

    // End of tests with privileged user.
    $account_switcher->switchBack();
  }

}
