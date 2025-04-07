<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Dynamically registers and alters container services.
 */
final class ProjectBrowserTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    assert(is_array($container->getParameter('container.modules')));
    if (array_key_exists('package_manager', $container->getParameter('container.modules'))) {
      $container->register(TestInstallReadiness::class, TestInstallReadiness::class)
        ->setAutowired(TRUE)
        ->addTag('event_subscriber');
    }
  }

}
