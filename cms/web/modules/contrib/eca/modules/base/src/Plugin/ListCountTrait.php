<?php

namespace Drupal\eca_base\Plugin;

/**
 * Trait for count action and condition to do the count work.
 */
trait ListCountTrait {

  /**
   * Receives a token and counts the contained items.
   *
   * @param string $name
   *   Name of token object which contains the list of which the items should
   *   be counted.
   *
   * @return int
   *   Number of items if given token exists and is either countable or
   *   traversable, 0 otherwise.
   */
  protected function countValue(string $name): int {
    $result = 0;
    if ($this->tokenService->hasTokenData($name)) {
      $data = $this->tokenService->getTokenData($name);
      if (is_countable($data)) {
        $result = count($data);
      }
      elseif ($data instanceof \Traversable) {
        $result = iterator_count($data);
      }
    }
    return $result;
  }

}
