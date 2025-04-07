<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for pre-configured actions.
 *
 * @group eca
 * @group eca_core
 */
class PreconfiguredActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    Role::create(['id' => 'test_role_eca_2', 'label' => 'Test Role ECA #2'])->save();
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    \Drupal::entityTypeManager()->getStorage('action')->create([
      'id' => 'user_add_eca_role',
      'label' => 'Add ECA user role',
      'type' => 'user',
      'plugin' => 'user_add_role_action',
      'status' => TRUE,
      'configuration' => [
        'rid' => 'test_role_eca',
      ],
    ])->save();
  }

  /**
   * Tests execution of pre-configured actions.
   */
  public function testExecution(): void {
    // This config does the following:
    // 1. It reacts upon the event when writing into the static array.
    // 2. It then executes the pre-configured action to add the ECA user role.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'array_write_process',
      'label' => 'ECA array write process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event for executing a pre-configured action.',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
          'successors' => [
            ['id' => 'execute_preconfigured_add_user_role', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'execute_preconfigured_add_user_role' => [
          'plugin' => 'eca_preconfigured_action:user_add_eca_role',
          'label' => 'Execute pre-configured add user role ECA',
          'configuration' => [],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    // Switch to admin user, as this one has permission to update the account.
    $user = User::load(1);
    $account_switcher->switchTo($user);

    $this->assertFalse($user->hasRole('test_role_eca'));
    $this->assertFalse($user->hasRole('test_role_eca_2'));

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    // Executing this action triggers the array write event.
    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();

    $user = User::load(1);
    $this->assertTrue($user->hasRole('test_role_eca'));
    $this->assertFalse($user->hasRole('test_role_eca_2'));
  }

  /**
   * Tests plugin configuration forms of pre-configured actions.
   */
  public function testConfigurationForm(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    // Switch to admin user to prevent any access issues in here.
    $user = User::load(1);
    $account_switcher->switchTo($user);

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Service\Actions $actions */
    $actions = \Drupal::service('eca.service.action');

    $not_preconfigured = $action_manager->createInstance('user_add_role_action');
    $preconfigured = $action_manager->createInstance('eca_preconfigured_action:user_add_eca_role');

    $form_state = new FormState();
    $preconfigured_form = $actions->getConfigurationForm($preconfigured, $form_state);
    $not_preconfigured_form = $actions->getConfigurationForm($not_preconfigured, $form_state);

    $this->assertNotSame($preconfigured_form, $not_preconfigured_form);
    $this->assertTrue(isset($preconfigured_form['object']));
    $this->assertTrue(isset($not_preconfigured_form['object']));
    $this->assertTrue(isset($not_preconfigured_form['rid']));
    $this->assertFalse(isset($preconfigured_form['rid']), "The role ID must not be provided as configuration input, because this is part of the preconfigured action.");
  }

  /**
   * Tests proper initialization of pre-configured actions.
   */
  public function testConfigurationInitialization(): void {
    // This config does the following:
    // 1. It reacts upon the event when writing into the static array.
    // 2. It then executes the pre-configured action to add the ECA user role.
    // 3. After that, it executes the non-preconfigured variant to add a
    //    user role (using a different role).
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'array_write_process',
      'label' => 'ECA array write process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event for executing a pre-configured action.',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
          ],
          'successors' => [
            ['id' => 'execute_preconfigured_add_user_role', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'execute_preconfigured_add_user_role' => [
          'plugin' => 'eca_preconfigured_action:user_add_eca_role',
          'label' => 'Execute pre-configured add user role ECA',
          'configuration' => [],
          'successors' => [
            ['id' => 'execute_non_preconfigured', 'condition' => ''],
          ],
        ],
        'execute_non_preconfigured' => [
          'plugin' => 'user_add_role_action',
          'label' => 'Execute non-pre-configured add user role ECA',
          'configuration' => [
            'rid' => 'test_role_eca_2',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    // Switch to admin user, as this one has permission to update the account.
    $user = User::load(1);
    $account_switcher->switchTo($user);

    $this->assertFalse($user->hasRole('test_role_eca'));
    $this->assertFalse($user->hasRole('test_role_eca_2'));

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    // Executing this action triggers the array write event.
    $action_manager->createInstance('eca_test_array_write', [
      'key' => 'mykey',
      'value' => 'myvalue',
    ])->execute();

    $user = User::load(1);
    $this->assertTrue($user->hasRole('test_role_eca'));
    $this->assertTrue($user->hasRole('test_role_eca_2'));
  }

}
