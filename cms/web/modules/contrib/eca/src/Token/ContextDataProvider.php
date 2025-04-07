<?php

namespace Drupal\eca\Token;

/**
 * Contextual data that is made available for any child process.
 */
class ContextDataProvider implements DataProviderInterface {

  /**
   * Stacked context data.
   *
   * The most recently added set of data is the first entry of this array.
   *
   * @var array
   */
  protected static array $stack = [];

  /**
   * {@inheritdoc}
   */
  public function getData(string $key): mixed {
    foreach (self::$stack as $set) {
      if (isset($set[$key])) {
        return $set[$key];
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return $this->getData($key) !== NULL;
  }

  /**
   * Push a new set of context data.
   *
   * @param array &$data
   *   The context data.
   */
  public function push(array &$data): void {
    array_unshift(self::$stack, $data);
  }

  /**
   * Removes the last added set of context data.
   */
  public function pop(): void {
    array_shift(self::$stack);
  }

}
