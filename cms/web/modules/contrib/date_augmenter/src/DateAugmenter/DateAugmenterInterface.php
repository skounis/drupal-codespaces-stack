<?php

namespace Drupal\date_augmenter\DateAugmenter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\date_augmenter\Plugin\ConfigurablePluginInterface;

/**
 * Provides an interface for augmenter plugins.
 *
 * @see \Drupal\date_augmenter\Annotation\SearchApiDateAugmenter
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginManager
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginBase
 * @see plugin_api
 */
interface DateAugmenterInterface extends ConfigurablePluginInterface {

  /**
   * Returns the weight for a specific processing stage.
   *
   * @return int
   *   The default weight for the given stage.
   *
   * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginManager::getProcessingStages()
   */
  public function getWeight();

  /**
   * Sets the weight for a specific processing stage.
   *
   * @param int $weight
   *   The weight for the given stage.
   *
   * @return $this
   *
   * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginManager::getProcessingStages()
   */
  public function setWeight($weight);

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
