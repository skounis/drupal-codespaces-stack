<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the "eca_state_write" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class EcaStateWriteTest extends KernelTestBase {

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
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests EcaStateWrite.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testEcaStateWrite(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\eca\EcaState $eca_state */
    $eca_state = \Drupal::service('eca.state');

    $token_services->addTokenData('myKey', 'my_key');
    $token_services->addTokenData('my_custom_token:value1', 'my_custom_value');

    /** @var \Drupal\eca_base\Plugin\Action\EcaStateWrite $action */
    $action = $action_manager->createInstance('eca_state_write', [
      'key' => '[myKey]',
      'value' => '[my_custom_token:value1]',
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertNotNull($eca_state->get('my_key'));
    $token_services->addTokenData('fromstate:value', $eca_state->get('my_key'));
    $this->assertEquals('my_custom_value', $token_services->replaceClear('[fromstate:value]'));
    $this->assertEquals('', $eca_state->get('my_key1'));

    $token_services->clearTokenData();
    /** @var \Drupal\eca_base\Plugin\Action\EcaStateWrite $action */
    $action = $action_manager->createInstance('eca_state_write', [
      'key' => 'my_key1',
      'value' => <<<YAML
key1: value1
key2: value2
YAML,
      'use_yaml' => TRUE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertNotNull($eca_state->get('my_key'));
    $token_services->addTokenData('fromstate:value', $eca_state->get('my_key'));
    $this->assertEquals('my_custom_value', $token_services->replaceClear('[fromstate:value]'));
    $this->assertSame([
      'key1' => 'value1',
      'key2' => 'value2',
    ], $eca_state->get('my_key1'));
  }

}
