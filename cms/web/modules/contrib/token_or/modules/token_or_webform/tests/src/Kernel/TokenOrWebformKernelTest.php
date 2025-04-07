<?php

namespace Drupal\Tests\token_or_webform\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Token Or module.
 *
 * @group token_or_webform
 * @requires module token
 */
class TokenOrWebformKernelTest extends KernelTestBase {

  /**
   * Token service.
   *
   * @var \Drupal\token\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'webform',
    'token',
    'user',
    'token_or',
    'token_or_webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = \Drupal::service('webform.token_manager');
  }

  /**
   * Test that current user token does fallback.
   */
  public function testToken() {
    $value = $this->tokenService->replace('[current-user:display-name|"foobar"]');
    $this->assertEquals('foobar', $value);
  }

  /**
   * Test that current user token array does fallback.
   */
  public function testArrayToken() {
    $value = $this->tokenService->replace(
      ['[current-user:email|"foobar"]', '[current-user:email|"foobar"]']
    );
    $this->assertEquals(['foobar', 'foobar'], $value);
  }

}
