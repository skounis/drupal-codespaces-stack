<?php

namespace Drupal\eca_render\Plugin\Action;

/**
 * The ResponsiveImage action deriver.
 */
class ResponsiveImageDeriver extends ModuleDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected static array $requiredModules = ['responsive_image'];

  /**
   * {@inheritdoc}
   */
  protected function buildDerivativeDefinitions(array $base_plugin_definition): void {
    $this->derivatives['responsive_image'] = $base_plugin_definition;
  }

}
