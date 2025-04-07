<?php

namespace Drupal\eca;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\State;

/**
 * Key/Value store for ECA only.
 */
class EcaState extends State {

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * ECA State constructor.
   *
   * This extends Drupal core's state service with an ECA related store.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   *
   * @noinspection MagicMethodsValidityInspection
   * @noinspection PhpMissingParentConstructorInspection
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, CacheBackendInterface $cache, LockBackendInterface $lock, TimeInterface $time) {
    // Do not call parent's constructor as we are overwriting its values.
    $this->keyValueStore = $key_value_factory->get('eca');
    $this->cid = 'eca';
    $this->cache = $cache;
    $this->tags = [];
    $this->lock = $lock;
    $this->time = $time;
  }

  /**
   * Stores the given or current time in ECA's key/value store.
   *
   * @param string $key
   *   The identifier for the timestamp.
   * @param int|null $timestamp
   *   (optional) The timestamp value to store. Skip this argument to store
   *   the current time.
   *
   * @return $this
   */
  public function setTimestamp(string $key, ?int $timestamp = NULL): EcaState {
    $this->set($this->timestampKey($key), $timestamp ?? $this->getCurrentTimestamp());
    return $this;
  }

  /**
   * Receive a stored timestamp from the ECA's key/value store.
   *
   * @param string $key
   *   The identifier for the timestamp.
   *
   * @return int
   *   The stored timestamp.
   */
  public function getTimestamp(string $key): int {
    return $this->get($this->timestampKey($key), 0);
  }

  /**
   * Receive the current time as timestamp.
   *
   * @return int
   *   The current timestamp.
   */
  public function getCurrentTimestamp(): int {
    return $this->time->getCurrentTime();
  }

  /**
   * Determine if the given state key has expired.
   *
   * @param string $key
   *   The identifier for the timestamp.
   * @param int $timeout
   *   Elapsed time in seconds after which the identified timestamp is
   *   considered to have timed-out.
   *
   * @return bool
   *   TRUE if the difference between current time and the identified and
   *   stored timestamp (default: 0) is greater than the given timeout period.
   *   FALSE otherwise.
   */
  public function hasTimestampExpired(string $key, int $timeout): bool {
    return ($this->getCurrentTimestamp() - $this->getTimestamp($key) > $timeout);
  }

  /**
   * Builds an identifier for timestamps related to a given key.
   *
   * @param string $key
   *   The identifier for the timestamp.
   *
   * @return string
   *   A unique key to identify a timestamp in the Key/Value store related to
   *   the given key.
   */
  protected function timestampKey(string $key): string {
    return implode('.', ['timestamp', $key]);
  }

}
