<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;

/**
 * Tests for ECA-extended Token replacement behavior.
 *
 * @group eca
 * @group eca_core
 */
class EcaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
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
   * Tests an invalid token field.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testInvalidTokenField(): void {
    $fields = [
      'list_token' => 'list',
      'token_name' => '[test]',
    ];
    /** @var \Drupal\eca\Entity\Eca $ecaConfig */
    $ecaConfig = Eca::create();
    $this->assertFalse($ecaConfig->addAction('12345', 'eca_count',
      'Action', $fields, []));
  }

  /**
   * Tests a valid token field.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testValidTokenField(): void {
    $fields = [
      'list_token' => 'list',
      'token_name' => 'test',
    ];
    /** @var \Drupal\eca\Entity\Eca $ecaConfig */
    $ecaConfig = Eca::create();
    $this->assertTrue($ecaConfig->addAction('12345', 'eca_count',
      'Action', $fields, []));
  }

}
