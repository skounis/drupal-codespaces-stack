<?php

namespace Drupal\eca_render\Plugin\Action;

/**
 * The Serialize action deriver.
 */
class SerializeDeriver extends ModuleDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected static array $requiredModules = ['serialization'];

  /**
   * {@inheritdoc}
   */
  protected function buildDerivativeDefinitions(array $base_plugin_definition): void {
    $this->derivatives['serialization'] = $base_plugin_definition;
  }

}
