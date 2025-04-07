<?php

namespace Drupal\Tests\token_or_webform\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Token Or Webform module.
 *
 * @group token_or_webform
 * @requires module token
 */
class TokenOrWebformKernelBrokenTest extends KernelTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = \Drupal::service('webform.token_manager');
  }

  /**
   * Test that current user token does not fallback.
   */
  public function testBrokenCurrentUserToken() {
    $value = $this->tokenService->replace('[current-user:email|"foobar"]');
    $this->assertEquals('', $value);
  }

  /**
   * Test that current user token array does not fallback.
   */
  public function testArrayToken() {
    $value = $this->tokenService->replace(
      ['[current-user:email|"foobar"]', '[current-user:email|"foobar"]']
    );
    $this->assertEquals(['', ''], $value);
  }

}
