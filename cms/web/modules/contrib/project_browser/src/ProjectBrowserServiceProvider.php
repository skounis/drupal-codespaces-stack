<?php

namespace Drupal\project_browser;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\project_browser\ComposerInstaller\Installer;
use Drupal\project_browser\ComposerInstaller\Validator\CoreNotUpdatedValidator;
use Drupal\project_browser\ComposerInstaller\Validator\PackageNotInstalledValidator;

/**
 * Dynamically registers and alters container services.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ProjectBrowserServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    assert(is_array($container->getParameter('container.modules')));
    if (array_key_exists('package_manager', $container->getParameter('container.modules'))) {
      parent::register($container);

      $container->register(Installer::class, Installer::class)
        ->setAutowired(TRUE);

      $container->register(CoreNotUpdatedValidator::class, CoreNotUpdatedValidator::class)
        ->addTag('event_subscriber')
        ->setAutowired(TRUE);

      $container->register(PackageNotInstalledValidator::class, PackageNotInstalledValidator::class)
        ->addTag('event_subscriber')
        ->setAutowired(TRUE);
    }
  }

}
