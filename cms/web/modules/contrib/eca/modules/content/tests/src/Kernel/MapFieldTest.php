<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTestMapField;
use Drupal\user\Entity\User;

/**
 * Kernel tests for getting and setting values from a map field.
 *
 * @group eca
 * @group eca_content
 */
class MapFieldTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_content',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_map_field');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests GetFieldValue on an entity that contains a map field.
   */
  public function testGetFieldValueMapEntity(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));

    // Create an entity having some values in its map field and then try to
    // access these values with a GetFieldValue action.
    $random_string = $this->randomString() . '&x';
    $entity = EntityTestMapField::create([
      'name' => $this->randomMachineName(16),
      'data' => [
        'random_string' => $random_string,
        'a_number' => 123,
        'another_value' => 'Hello',
      ],
    ]);
    $entity->save();

    /** @var \Drupal\eca_content\Plugin\Action\GetFieldValue $action */
    $action = $action_manager->createInstance('eca_get_field_value', [
      'token_name' => 'my_custom_token:random_string',
      'field_name' => 'data.random_string',
    ]);
    $this->assertSame($entity->access('view'), $action->access($entity));

    /** @var \Drupal\eca_content\Plugin\Action\GetFieldValue $action */
    $action = $action_manager->createInstance('eca_get_field_value', [
      'token_name' => 'my_custom_token:random_string',
      'field_name' => 'data.random_string',
    ]);
    $this->assertSame($entity->access('view'), $action->access($entity));
    $action->execute($entity);
    $this->assertEquals($random_string, (string) $token_services->replaceClear('[my_custom_token:random_string]'));
    $this->assertEquals('', $token_services->replaceClear('[my_custom_token:another_value]'));

    /** @var \Drupal\eca_content\Plugin\Action\GetFieldValue $action */
    $action = $action_manager->createInstance('eca_get_field_value', [
      'token_name' => 'another_token',
      'field_name' => 'data',
    ]);
    $action->execute($entity);
    $this->assertEquals($random_string, (string) $token_services->replaceClear('[another_token:random_string]'));
    $this->assertEquals('123', (string) $token_services->replaceClear('[another_token:a_number]'));
    $this->assertEquals('Hello', (string) $token_services->replaceClear('[another_token:another_value]'));

    $account_switcher->switchBack();
  }

  /**
   * Tests SetFieldValue on an entity that contains a map field.
   */
  public function testSetFieldValueMapEntity(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));

    // Create an entity having some values in its map field and then try to
    // set and change these values with a SetFieldValue action.
    $random_string = $this->randomString() . '&x';
    $entity = EntityTestMapField::create([
      'name' => $this->randomMachineName(16),
      'data' => [
        'random_string' => $random_string,
        'a_number' => 123,
        'another_value' => 'Hello',
      ],
    ]);
    $entity->save();

    // Action plugin configuration defaults.
    $defaults = [
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => FALSE,
    ];

    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $action_manager->createInstance('eca_set_field_value', [
      'method' => 'set:clear',
      'field_name' => 'data.another_value',
      'field_value' => 'Good bye',
    ] + $defaults);
    $this->assertSame($entity->access('update'), $action->access($entity));
    $this->assertEquals('Hello', $entity->data->another_value);

    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $action_manager->createInstance('eca_set_field_value', [
      'method' => 'set:clear',
      'field_name' => 'data.another_value',
      'field_value' => 'Good bye',
    ] + $defaults);
    $this->assertSame($entity->access('update'), $action->access($entity));
    $action->execute($entity);
    $this->assertEquals('Good bye', $entity->data->another_value);
    $this->assertEquals($random_string, $entity->data->random_string);
    $this->assertEquals(123, $entity->data->a_number);

    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $action_manager->createInstance('eca_set_field_value', [
      'method' => 'set:clear',
      'field_name' => 'data.a_new_one',
      'field_value' => 'A new one!',
    ] + $defaults);
    $action->execute($entity);
    $this->assertEquals('A new one!', $entity->data->a_new_one);
    $this->assertEquals('Good bye', $entity->data->another_value);
    $this->assertEquals($random_string, $entity->data->random_string);
    $this->assertEquals(123, $entity->data->a_number);

    $account_switcher->switchBack();
  }

}
