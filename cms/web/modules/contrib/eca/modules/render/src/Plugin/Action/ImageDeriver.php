<?php

namespace Drupal\eca_render\Plugin\Action;

/**
 * The Image action deriver.
 */
class ImageDeriver extends ModuleDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected static array $requiredModules = ['image'];

  /**
   * {@inheritdoc}
   */
  protected function buildDerivativeDefinitions(array $base_plugin_definition): void {
    $this->derivatives['image'] = $base_plugin_definition;
  }

}
