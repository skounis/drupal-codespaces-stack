<?php

namespace Drupal\eca\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * An interface implemented by objects that hold a plugin instance.
 */
interface ObjectWithPluginInterface {

  /**
   * Get the plugin instance.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface
   *   The plugin instance.
   */
  public function getPlugin(): PluginInspectionInterface;

}
