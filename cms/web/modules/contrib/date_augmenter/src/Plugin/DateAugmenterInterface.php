<?php

namespace Drupal\date_augmenter\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines an interface for Date augmenter plugins.
 */
interface DateAugmenterInterface extends PluginInspectionInterface {

  /**
   * Builds and returns a render array for the task.
   *
   * @param array $output
   *   The existing render array, to be augmented.
   * @param Drupal\Core\Datetime\DrupalDateTime $start
   *   The object which contains the start time.
   * @param Drupal\Core\Datetime\DrupalDateTime $end
   *   The optionalobject which contains the end time.
   * @param array $options
   *   An array of options to further guide output.
   *
   * @return array
   *   Returns a render array for the output of the task;
   */
  public function augmentOutput(array &$output, DrupalDateTime $start, DrupalDateTime $end = NULL, array $options = []);

}
