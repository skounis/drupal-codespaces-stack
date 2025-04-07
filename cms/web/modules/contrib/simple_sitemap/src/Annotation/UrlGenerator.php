<?php

namespace Drupal\simple_sitemap\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a UrlGenerator item annotation object.
 *
 * @see \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
 * @see plugin_api
 *
 * @Annotation
 */
class UrlGenerator extends Plugin {

  /**
   * The generator ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the generator.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the generator.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Default generator settings.
   *
   * @var array
   */
  public $settings = [];

}
