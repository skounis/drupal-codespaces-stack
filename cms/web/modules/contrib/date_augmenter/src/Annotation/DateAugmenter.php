<?php

namespace Drupal\date_augmenter\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Date augmenter item annotation object.
 *
 * @see \Drupal\date_augmenter\Plugin\DateAugmenterManager
 * @see plugin_api
 *
 * @Annotation
 */
class DateAugmenter extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the date augmenter type.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * An integer to determine the weight of this date augmenter.
   *
   * Optional. This is relative to other date augmenters in the Field UI
   * when selecting date augmenters for a given field instance.
   *
   * @var int
   */
  public $weight = NULL;

}
