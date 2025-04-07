<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the "eca_state_read" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class EcaStateReadTest extends KernelTestBase {

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
   * Tests read value and add token.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAddToken(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('mykey', 'test_key');
    /** @var \Drupal\eca\EcaState $eca_state */
    $eca_state = \Drupal::service('eca.state');
    $eca_state->set('test_key', 'my_token');

    /** @var \Drupal\eca_base\Plugin\Action\EcaStateRead $action */
    $action = $action_manager->createInstance('eca_state_read', [
      'key' => '[myKey]',
      'token_name' => 'my_custom_token:value1',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals('my_token', $token_services->replaceClear('[my_custom_token:value1]'));
    $this->assertEquals('', $token_services->replaceClear('[my_custom_token:value3]'));
  }

}
