<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the "eca_token_set_context" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class TokenSetContextTest extends KernelTestBase {

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
   * Tests TokenSetContext.
   */
  public function testTokenSetContext(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $random_string = $this->randomString();
    $token_services->addTokenData('mytoken:value', $random_string);

    $this->assertEquals($random_string, $token_services->replaceClear('[mytoken:value]'));

    /** @var \Drupal\eca_base\Plugin\Action\TokenSetContext $action */
    $action = $action_manager->createInstance('eca_token_set_context', []);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);

    $token_services->clearTokenData();

    $this->assertEquals($random_string, $token_services->replaceClear('[mytoken:value]'), "Data must still be available, because it was set as context.");

    /** @var \Drupal\eca_base\Plugin\Action\TokenSetContext $action */
    $action = $action_manager->createInstance('eca_token_set_context', []);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);

    $this->assertEquals($random_string, $token_services->replaceClear('[mytoken:value]'), "Data must still be available, despite one nested call stacked empty data.");

    $action->cleanupAfterSuccessors();

    $this->assertEquals($random_string, $token_services->replaceClear('[mytoken:value]'), "Data must still be available, despite one nested call got cleaned up.");

    $action->cleanupAfterSuccessors();

    $this->assertEquals('', $token_services->replaceClear('[mytoken:value]'));
  }

}
