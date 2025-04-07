<?php

namespace Drupal\symfony_mailer_lite\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines symfony_mailer_lite_transport annotation object.
 *
 * @Annotation
 */
class SymfonyMailerLiteTransport extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
