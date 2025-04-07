<?php

namespace Drupal\Tests\token_or\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Token Or module.
 *
 * @group token_or
 * @requires module token
 */
class TokenOrTest extends KernelTestBase {

  /**
   * The string replacement value for these tests.
   *
   * @var string
   */
  protected static $stringReplacement = 'baz';

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
    'token',
    'token_or',
    'token_or_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenService = \Drupal::service('token');
  }

  /**
   * Tests the basic functionality.
   */
  public function testTokenReplacement() {
    $value = $this->tokenService->replace('[token_or:test|token_or:test2]');
    $this->assertEquals('test', $value);

    $value = $this->tokenService->replace('[token_or:empty|token_or:empty2]');
    $this->assertEmpty($value);

    $value = $this->tokenService->replace('[token_or:empty|token_or:empty2]/[token_or:test]');
    $this->assertEquals('/test', $value);

    $value = $this->tokenService->replace('[token_or:empty|token_or:empty2]/[token_or:empty|token_or:test]/[token_or:test2]');
    $this->assertEquals('/test/test2', $value);

    $value = $this->tokenService->replace('[token_or:empty|token_or:empty2]/[token_or:empty2|token_or:empty]/[token_or:test2]');
    $this->assertEquals('//test2', $value);
  }

  /**
   * Tests the string replacement functionality.
   */
  public function testStringReplacement() {
    $value = $this->tokenService->replace('[token_or:empty|"' . self::$stringReplacement . '"]');
    $this->assertEquals(self::$stringReplacement, $value);
  }

  /**
   * Test the validation of the basic functionality.
   */
  public function testTokenValidation() {
    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|token_or:test2]', ['token_or']);
    $this->assertEmpty($invalid_tokens);
  }

  /**
   * Test the validation of the basic functionality.
   */
  public function testStringValidation() {
    $invalid_tokens = $this->tokenService->getInvalidTokensByContext('[token_or:test|"' . self::$stringReplacement . '"]', ['token_or']);
    $this->assertEmpty($invalid_tokens);
  }

  /**
   * Tests the multiple token functionality.
   */
  public function testMultipleTokens() {
    $value = $this->tokenService->replace('[token_or:test] [token_or:test|token_or:test2]');
    $this->assertEquals('test test', $value);
  }

  /**
   * Tests the null replacement functionality.
   */
  public function testNullReplacement() {
    $value = $this->tokenService->replace(NULL);
    $this->assertEquals(NULL, $value);
  }

  /**
   * Tests that scan() returns strings.
   */
  public function testScanReturnsStrings() {
    $value = $this->tokenService->scan('/[node:field_dummy]/dummy?type:[node:field_test|node:field_dummy]');
    $this->assertEquals([
      'node' => [
        'field_test' => '[node:field_test]',
        'field_dummy' => '[node:field_dummy]',
      ],
    ], $value);
  }

}
