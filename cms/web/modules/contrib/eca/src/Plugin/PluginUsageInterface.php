<?php

namespace Drupal\eca\Plugin;

use Drupal\eca\Entity\Eca;

/**
 * Interface for ECA plugins that require to act when being added to ECA.
 */
interface PluginUsageInterface {

  /**
   * Allows event, condition and action plugins to act when added to ECA.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA entity.
   * @param string $id
   *   The ID of either event, condition or action in the modeller.
   */
  public function pluginUsed(Eca $eca, string $id): void;

}
