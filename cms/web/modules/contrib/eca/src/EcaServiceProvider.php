<?php

namespace Drupal\eca;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\eca\Token\ContribToken;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provider for dynamically provided services by ECA.
 */
class EcaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Add our dynamic subscriber to the event dispatcher.
    $definition = $container->getDefinition('event_dispatcher');
    $definition->addMethodCall('addSubscriber', [new Reference('eca.dynamic_subscriber')]);

    if (class_exists('Drupal\token\Token')) {
      // Replace the core decorator with the contrib decorator.
      $definition = $container->getDefinition('eca.service.token');
      $definition->setClass(ContribToken::class);
    }
  }

}
