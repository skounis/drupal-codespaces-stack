<?php

namespace Drupal\eca\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines ECA condition annotation object.
 *
 * @Annotation
 */
class EcaCondition extends Plugin {

  /**
   * The condition plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the condition.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public Translation $label;

  /**
   * The name of the module providing the type.
   *
   * @var string
   */
  public string $module;

  /**
   * An array of context definitions describing the context used by the plugin.
   *
   * The array is keyed by context names.
   *
   * @var \Drupal\Core\Annotation\ContextDefinition[]
   */
  public array $context_definitions = [];

  /**
   * The category under which the condition should listed in the UI.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $category;

}
