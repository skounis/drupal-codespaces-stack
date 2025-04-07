<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_token_set_value" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class TokenSetValueTest extends KernelTestBase {

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
   * Tests TokenSetValue.
   */
  public function testTokenSetValue(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $random_string = $this->randomString();
    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $action_manager->createInstance('eca_token_set_value', [
      'token_name' => 'my_custom_token:value1',
      'token_value' => $random_string,
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals($random_string, $token_services->replaceClear('[my_custom_token:value1]'));
    $this->assertEquals('', $token_services->replaceClear('[my_custom_token:value2]'));

    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $action_manager->createInstance('eca_token_set_value', [
      'token_name' => 'my_custom_token:value2',
      'token_value' => $random_string . '123',
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals($random_string, $token_services->replaceClear('[my_custom_token:value1]'));
    $this->assertEquals($random_string . '123', $token_services->replaceClear('[my_custom_token:value2]'));

    $this->assertEquals(Yaml::encode([
      'value1' => $random_string,
      'value2' => $random_string . '123',
    ]), $token_services->replace('[my_custom_token]'));

    $yaml_string = <<<YAML
0: "Hello"
1: "[my_custom_token:value1]"
YAML;

    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $action_manager->createInstance('eca_token_set_value', [
      'token_name' => 'object_token',
      'token_value' => $yaml_string,
      'use_yaml' => TRUE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals('Hello', $token_services->replaceClear('[object_token:0]'));
    $this->assertEquals($random_string, $token_services->replaceClear('[object_token:1]'));

    $user = User::create([
      'mail' => 'test@eca.local',
      'name' => 'Test user',
    ]);
    $token_services->addTokenData('new_user', $user);
    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $action_manager->createInstance('eca_token_set_value', [
      'token_name' => 'user_name',
      'token_value' => '[new_user:name]',
      'use_yaml' => FALSE,
    ]);
    $action->execute(NULL);
    $this->assertInstanceOf(DataTransferObject::class, $token_services->getTokenData('user_name'), "Value must be wrapped by a DTO, and not a field item list.");
  }

}
