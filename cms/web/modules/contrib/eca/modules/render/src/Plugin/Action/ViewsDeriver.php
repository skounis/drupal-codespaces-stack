<?php

namespace Drupal\eca_render\Plugin\Action;

/**
 * The Views action deriver.
 */
class ViewsDeriver extends ModuleDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected static array $requiredModules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected function buildDerivativeDefinitions(array $base_plugin_definition): void {
    $this->derivatives['views'] = $base_plugin_definition;
  }

}
