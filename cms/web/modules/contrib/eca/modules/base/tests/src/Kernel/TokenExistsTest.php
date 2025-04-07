<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\Token\TokenInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_token_exists" condition plugin.
 *
 * @group eca
 * @group eca_base
 */
class TokenExistsTest extends KernelTestBase {

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
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    $this->installConfig(static::$modules);
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests the "eca_token_exists" condition plugin.
   */
  public function testTokenExists(): void {
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'not_existing',
    ]);
    $this->assertFalse($condition->evaluate());

    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'user',
    ]);
    $this->assertTrue($condition->evaluate());

    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[user]',
    ]);
    $this->assertTrue($condition->evaluate(), "Must produce the same result as when using without brackets.");

    $empty_dto = DataTransferObject::create();
    $this->tokenService->addTokenData('users', $empty_dto);
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'users',
    ]);
    $this->assertFalse($condition->evaluate());
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[users]',
    ]);
    $this->assertFalse($condition->evaluate(), "Must produce the same result as when using without brackets.");

    $not_empty_dto = DataTransferObject::create([
      User::load(0),
      User::load(1),
      User::load(2),
    ]);
    $this->tokenService->addTokenData('users', $not_empty_dto);
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'users',
    ]);
    $this->assertTrue($condition->evaluate());
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[users]',
    ]);
    $this->assertTrue($condition->evaluate(), "Must produce the same result as when using without brackets.");

    $this->tokenService->addTokenData('users', $not_empty_dto);
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'site:name',
    ]);
    $this->assertFalse($condition->evaluate());
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[site:name]',
    ]);
    $this->assertFalse($condition->evaluate(), "Must produce the same result as when using without brackets.");

    \Drupal::configFactory()->getEditable('system.site')->set('name', 'My site')->save();
    $this->tokenService->addTokenData('users', $not_empty_dto);
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'site:name',
    ]);
    $this->assertTrue($condition->evaluate());
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[site:name]',
    ]);
    $this->assertTrue($condition->evaluate(), "Must produce the same result as when using without brackets.");

    // Test that the token name can be provided as a token itself.
    $string1 = 'abc';
    $string2 = 'def';
    $this->tokenService->addTokenData('mytoken1', $string1);
    $this->tokenService->addTokenData('mytoken2', $string2);
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'mytoken1',
    ]);
    $this->assertTrue($condition->evaluate(), "My token1 should exist");
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => 'mytoken2',
    ]);
    $this->assertTrue($condition->evaluate(), "My token2 should exist");
    $condition = $this->conditionManager->createInstance('eca_token_exists', [
      'token_name' => '[placeholder]',
    ]);
    $this->assertFalse($condition->evaluate(), "Placeholder should not exist");
    $this->tokenService->addTokenData('placeholder', 'mytoken1');
    $this->assertTrue($condition->evaluate(), "Placeholder now should exist");
    // @todo Disabled until we addressed token support for token_name.
    // @see https://www.drupal.org/project/eca/issues/3302569
    // $this->tokenServices->addTokenData('placeholder', 'mytoken3');
    // @codingStandardsIgnoreLine
    // $this->assertFalse($condition->evaluate(), "Placeholder should not exist");
    $this->tokenService->addTokenData('placeholder', 'mytoken2');
    $this->assertTrue($condition->evaluate(), "Placeholder now should exist again");
  }

}
