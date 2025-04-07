<?php

namespace Drupal\eca_render\Plugin\Action;

/**
 * The Text action deriver.
 */
class TextDeriver extends ModuleDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected static array $requiredModules = ['filter'];

  /**
   * {@inheritdoc}
   */
  protected function buildDerivativeDefinitions(array $base_plugin_definition): void {
    $this->derivatives['filter'] = $base_plugin_definition;
  }

}
