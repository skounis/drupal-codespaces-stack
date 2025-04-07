<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\PluginManager\Action;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;

/**
 * Kernel tests for the decorator of the action manager.
 *
 * @group eca
 * @group eca_core
 */
class ActionDecoratorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
  }

  /**
   * Tests the expected behavior of the action manager decorator.
   */
  public function testDecorator(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $decorated_manager = Action::get()->getDecoratedActionManager();
    $this->assertTrue($action_manager instanceof Action, "Action manager must be the decorator.");
    $this->assertSame($action_manager, Action::get());
    $this->assertNotSame($action_manager, $decorated_manager);
    $this->assertFalse($decorated_manager instanceof Action);
    $this->assertTrue($decorated_manager instanceof ActionManager);

    $filtered_definitions = $action_manager->getDefinitions();
    $unfiltered_definitions = $decorated_manager->getDefinitions();
    $this->assertTrue(isset($filtered_definitions['action_send_email_action']));
    $this->assertFalse(isset($filtered_definitions['eca_test_array_increment']));
    $this->assertFalse(isset($filtered_definitions['eca_test_array_write']));
    $this->assertTrue(isset($unfiltered_definitions['action_send_email_action']));
    $this->assertTrue(isset($unfiltered_definitions['eca_test_array_increment']));
    $this->assertTrue(isset($unfiltered_definitions['eca_test_array_write']));

    $this->assertTrue($action_manager->hasDefinition('eca_test_array_write'), "Decorator must have definition when explicitly requested.");
    $this->assertTrue($decorated_manager->hasDefinition('eca_test_array_write'));
    $this->assertTrue($action_manager->createInstance('eca_test_array_write') instanceof ArrayWrite);
    $this->assertTrue($decorated_manager->createInstance('eca_test_array_write') instanceof ArrayWrite);
  }

}
