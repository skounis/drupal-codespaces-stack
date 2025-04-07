<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\search_api\Kernel\TestTimeService;

/**
 * A variant of the memory cache backend that allows to change the request time.
 *
 * @todo Remove once we depend on Drupal 10.3.
 */
class TestMemoryBackend extends MemoryBackend {

  /**
   * The simulated request time.
   *
   * @var int|null
   */
  protected ?int $requestTime = NULL;

  /**
   * Constructs a new class instance.
   */
  public function __construct() {
    DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '10.3',
      fn () => parent::__construct(new TestTimeService()),
      fn () => NULL,
    );
  }

  /**
   * Returns the timestamp of the current request.
   *
   * @return int
   *   The request timestamp.
   */
  public function getRequestTime(): int {
    return DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '10.3',
      fn () => $this->requestTime ?: $this->time->getRequestTime(),
      fn () => $this->requestTime ?: parent::getRequestTime(),
    );
  }

  /**
   * Sets the request time.
   *
   * @param int $time
   *   The request time to set.
   */
  public function setRequestTime(int $time) {
    $this->requestTime = $time;
    return DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '10.3',
      fn () => $this->time->setRequestTime($time),
      fn () => NULL,
    );
  }

}
