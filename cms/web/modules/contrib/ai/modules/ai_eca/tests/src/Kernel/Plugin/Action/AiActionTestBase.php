<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\TestTools\Random;

/**
 * Base class for AI Action tests.
 */
abstract class AiActionTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_eca',
    'ai_test',
    'eca',
    'file',
    'key',
    'system',
    'user',
  ];

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installConfig(static::$modules);

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * {@inheritdoc}
   */
  public function randomString($length = 8) {
    // We adapt the randomString method to make sure strings returned by it
    // are not modified by Yaml::encode because the ECA module does that when
    // replacing tokens and that can lead to random errors.
    // For example A'\B becomes A''\B when passed through encoding.
    do {
      // Most random strings do not get altered when encoded.
      // So this does not loop infinitely.
      $string = Random::string($length);
    } while (sprintf("'%s'", $string) !== Yaml::encode($string));
    return $string;
  }

}
