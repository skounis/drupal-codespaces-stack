<?php

namespace Drupal\eca\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ECA modeller annotation object.
 *
 * @Annotation
 */
class EcaModeller extends Plugin {

  /**
   * Label of the modeller.
   *
   * @var string
   */
  public string $label;

  /**
   * Description of the modeller.
   *
   * @var string
   */
  public string $description;

}
