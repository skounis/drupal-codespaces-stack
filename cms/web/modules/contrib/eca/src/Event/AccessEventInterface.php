<?php

namespace Drupal\eca\Event;

use Drupal\Core\Access\AccessResultInterface;

/**
 * Interface for access events.
 */
interface AccessEventInterface extends AccountEventInterface {

  /**
   * Get the access result.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|null
   *   The access result, or NULL if no result was calculated.
   */
  public function getAccessResult(): ?AccessResultInterface;

  /**
   * Set the access result.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $result
   *   The access result to set.
   *
   * @return $this
   */
  public function setAccessResult(AccessResultInterface $result): AccessEventInterface;

}
