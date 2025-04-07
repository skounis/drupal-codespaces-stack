<?php

namespace Drupal\Tests\eca_cache\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Kernel tests for the eca_cache module.
 *
 * @group eca
 * @group eca_cache
 */
class CacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_cache',
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
   * Tests actions of eca_cache.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testEcaCacheActions(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $cache_key = 'eca_cache:' . $this->randomMachineName();

    $defaults = [
      'backend' => 'eca_chained',
      'key' => $cache_key,
    ];

    $yaml = <<<YAML
key1: value1
key2: value2
YAML;

    /** @var \Drupal\eca_cache\Plugin\Action\EcaCacheWrite $action */
    $action = $action_manager->createInstance('eca_cache_write', [
      'value' => $yaml,
      'expire' => '-1',
      'tags' => 'mytag, mytag2',
      'use_yaml' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = \Drupal::service('cache.eca_chained');

    $cached = $cache->get($cache_key);
    $this->assertNotFalse($cached);
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $cached->data);

    /** @var \Drupal\eca_cache\Plugin\Action\EcaCacheRead $action */
    $action = $action_manager->createInstance('eca_cache_read', [
      'token_name' => 'mytoken',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $this->assertFalse($token_services->hasTokenData('mytoken'));
    $action->execute();

    $this->assertTrue($token_services->hasTokenData('mytoken'));
    $this->assertInstanceOf(DataTransferObject::class, $token_services->getTokenData('mytoken'));
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $token_services->getTokenData('mytoken')->toArray());

    /** @var \Drupal\eca_cache\Plugin\Action\EcaCacheInvalidate $action */
    $action = $action_manager->createInstance('eca_cache_invalidate', [
      'tags' => 'mytag3',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $cached = $cache->get($cache_key);
    $this->assertNotFalse($cached);
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $cached->data);

    /** @var \Drupal\eca_cache\Plugin\Action\EcaCacheInvalidate $action */
    $action = $action_manager->createInstance('eca_cache_invalidate', [
      'tags' => 'mytag2',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $cached = $cache->get($cache_key);
    $this->assertFalse($cached);
  }

  /**
   * Tests actions of raw eca_cache.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testRawCacheActions(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $cache_backends = Cache::getBins();
    $cache_backend_keys = array_keys($cache_backends);
    $cache_backend = reset($cache_backend_keys);
    $cache = $cache_backends[$cache_backend];
    $cache_key = $this->randomMachineName();

    $defaults = [
      'backend' => $cache_backend,
      'key' => $cache_key,
    ];

    $yaml = <<<YAML
key1: value1
key2: value2
YAML;

    /** @var \Drupal\eca_cache\Plugin\Action\RawCacheWrite $action */
    $action = $action_manager->createInstance('eca_raw_cache_write', [
      'value' => $yaml,
      'expire' => '-1',
      'tags' => 'mytag, mytag2',
      'use_yaml' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $cached = $cache->get($cache_key);
    $this->assertNotFalse($cached);
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $cached->data);

    /** @var \Drupal\eca_cache\Plugin\Action\RawCacheRead $action */
    $action = $action_manager->createInstance('eca_raw_cache_read', [
      'token_name' => 'mytoken',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $this->assertFalse($token_services->hasTokenData('mytoken'));
    $action->execute();

    $this->assertTrue($token_services->hasTokenData('mytoken'));
    $this->assertInstanceOf(DataTransferObject::class, $token_services->getTokenData('mytoken'));
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $token_services->getTokenData('mytoken')->toArray());

    /** @var \Drupal\eca_cache\Plugin\Action\RawCacheInvalidate $action */
    $action = $action_manager->createInstance('eca_raw_cache_invalidate', [
      'tags' => 'mytag3',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $cached = $cache->get($cache_key);
    $this->assertNotFalse($cached);
    $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $cached->data);

    /** @var \Drupal\eca_cache\Plugin\Action\RawCacheInvalidate $action */
    $action = $action_manager->createInstance('eca_raw_cache_invalidate', [
      'tags' => 'mytag2',
    ] + $defaults);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $cached = $cache->get($cache_key);
    $this->assertFalse($cached);
  }

}
