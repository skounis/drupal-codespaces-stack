<?php

namespace Drupal\klaro;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the JsCollectionRenderer service.
 */
class KlaroServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('asset.js.collection_renderer');
    $definition->setClass('Drupal\klaro\KlaroJsCollectionRenderer');
  }

}
