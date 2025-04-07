<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_current_user_id" condition plugin.
 *
 * @group eca
 * @group eca_user
 */
class CurrentUserIdTest extends KernelTestBase {

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
   * Tests CurrentUserId.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testCurrentUserId(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\eca_user\Plugin\ECA\Condition\CurrentUserId $condition */
    $condition = $condition_manager->createInstance('eca_current_user_id', ['user_id' => '3']);
    $this->assertFalse($condition->evaluate(), 'User ID 3 must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_id', ['user_id' => '0']);
    $this->assertTrue($condition->evaluate(), 'User ID 0 must evaluate to true.');

    // Switch to authenticated user.
    $account_switcher->switchTo(User::load(2));

    $condition = $condition_manager->createInstance('eca_current_user_id', ['user_id' => '3']);
    $this->assertFalse($condition->evaluate(), 'User ID 3 must still evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_id', ['user_id' => '0']);
    $this->assertFalse($condition->evaluate(), 'User ID 0 must evaluate to false.');

    $condition = $condition_manager->createInstance('eca_current_user_id', ['user_id' => '2']);
    $this->assertTrue($condition->evaluate(), 'User ID 2 must evaluate to true.');

    // End of tests with authenticated user.
    $account_switcher->switchBack();
  }

}
