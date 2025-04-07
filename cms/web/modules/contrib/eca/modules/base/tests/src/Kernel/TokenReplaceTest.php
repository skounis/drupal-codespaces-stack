<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_token_replace" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class TokenReplaceTest extends KernelTestBase {

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
   * Tests TokenReplace.
   */
  public function testTokenReplace(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $token_text = "This text contains a [token:value]";
    $token_services->addTokenData('token:text', $token_text);
    $token_services->addTokenData('token:value', 'dynamic token value');

    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $action_manager->createInstance('eca_token_set_value', [
      'token_name' => 'my_custom_token:value1',
      'token_value' => '[token:text]',
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals("This text contains a [token:value]", $token_services->replace('[my_custom_token:value1]'), "Recursive token replacement must not happen with eca_token_set_value.");

    /** @var \Drupal\eca_base\Plugin\Action\TokenReplace $action */
    $action = $action_manager->createInstance('eca_token_replace', [
      'token_name' => 'my_custom_token:value2',
      'token_value' => '[token:text]',
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals("This text contains a dynamic token value", $token_services->replace('[my_custom_token:value2]'));

    $yaml_string = <<<YAML
0: "Hello"
1: "[token:text]"
YAML;

    /** @var \Drupal\eca_base\Plugin\Action\TokenReplace $action */
    $action = $action_manager->createInstance('eca_token_replace', [
      'token_name' => 'object_token',
      'token_value' => $yaml_string,
      'use_yaml' => TRUE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals('Hello', $token_services->replaceClear('[object_token:0]'));
    $this->assertEquals("This text contains a dynamic token value", $token_services->replace('[object_token:1]'));

    $user = User::create([
      'mail' => 'test@eca.local',
      'name' => '[token:text]',
    ]);
    $token_services->addTokenData('new_user', $user);

    /** @var \Drupal\eca_base\Plugin\Action\TokenReplace $action */
    $action = $action_manager->createInstance('eca_token_replace', [
      'token_name' => 'replaced',
      'token_value' => '[new_user:name:value]',
      'use_yaml' => FALSE,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals("[token:text]", $user->name->value, "The property of an entity must not be directly manipulated.");
    $this->assertEquals("This text contains a dynamic token value", $token_services->replace('[replaced]'));
  }

}
