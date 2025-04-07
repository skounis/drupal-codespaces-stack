<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\eca\EcaState;

/**
 * Tests to EcaState class.
 *
 * @group eca
 * @group eca_core
 */
class EcaStateTest extends EcaUnitTestBase {

  private const TEST_KEY = 'test_key';

  /**
   * Key value factory service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValueStore;

  /**
   * The cache backend that should be used.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The lock backend that should be used.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $this->keyValueStore = $this->createMock(KeyValueStoreInterface::class);
    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
  }

  /**
   * Tests if the timestamp has expired.
   */
  public function testIfTimestampHasExpired(): void {
    // 2018/01/09 15:00:00
    $storedTimestamp = 1515506400;
    // 2018/01/09 16:00:00
    $currentTimestamp = 1515510000;
    $this->keyValueStore->expects($this->once())->method('get')
      ->with('timestamp.' . self::TEST_KEY)->willReturn($storedTimestamp);
    $this->keyValueFactory->expects($this->once())->method('get')
      ->with('eca')->willReturn($this->keyValueStore);

    $this->time->expects($this->exactly(3))->method('getCurrentTime')
      ->willReturn($currentTimestamp);

    $ecaState = new EcaState($this->keyValueFactory, $this->cache, $this->lock, $this->time);
    $this->assertEquals($currentTimestamp, $ecaState->getCurrentTimestamp());
    $this->assertTrue($ecaState->hasTimestampExpired(self::TEST_KEY, 3599));
    $this->assertFalse($ecaState->hasTimestampExpired(self::TEST_KEY, 3600));
  }

  /**
   * Tests timestampKey method.
   *
   * @throws \ReflectionException
   */
  public function testTimestampKey(): void {
    $ecaState = new EcaState($this->keyValueFactory, $this->cache, $this->lock, $this->time);
    $result = $this->getPrivateMethod(EcaState::class, 'timestampKey')
      ->invokeArgs($ecaState, [self::TEST_KEY]);

    $this->assertEquals('timestamp.test_key', $result);
  }

  /**
   * Tests the get and set methods.
   */
  public function testGetterAndSetter(): void {
    // 2018/01/09 16:00:00
    $currentTimestamp = 1515510000;
    $this->time->expects($this->once())->method('getCurrentTime')
      ->willReturn($currentTimestamp);
    $this->keyValueFactory->expects($this->once())->method('get')
      ->with('eca')->willReturn($this->keyValueStore);
    $ecaState = new EcaState($this->keyValueFactory, $this->cache, $this->lock, $this->time);
    $ecaState->setTimestamp(self::TEST_KEY);
    $this->assertEquals($currentTimestamp, $ecaState->getTimestamp(self::TEST_KEY));
  }

}
