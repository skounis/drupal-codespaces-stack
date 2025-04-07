<?php

namespace Drupal\scheduler\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a process plugin for the hide_seconds global setting.
 *
 * The hide_seconds setting does not exist in Drupal 7 because the entire date
 * and time input format could be specified. However we can use the date format
 * as source input here and set hide_seconds to true if the seconds were not
 * included in the full date format.
 *
 * @MigrateProcessPlugin(
 *   id = "scheduler_hide_seconds"
 * )
 */
class SchedulerHideSeconds extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The value of hide_seconds is set to true if the source date format does
    // not contain seconds (lower case 's').
    $hide_seconds = !strstr($value, 's');
    return $hide_seconds;
  }

}
