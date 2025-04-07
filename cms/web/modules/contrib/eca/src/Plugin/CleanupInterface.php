<?php

namespace Drupal\eca\Plugin;

/**
 * Interface for ECA plugins that require to perform a cleanup.
 */
interface CleanupInterface {

  /**
   * Performs a cleanup after this plugin and all its successors were run.
   */
  public function cleanupAfterSuccessors(): void;

}
