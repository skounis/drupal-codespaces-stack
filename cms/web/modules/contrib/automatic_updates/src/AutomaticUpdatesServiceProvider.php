<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\automatic_updates\Validator\PhpExtensionsValidator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\package_manager\Validator\PhpExtensionsValidator as PackageManagerPhpExtensionsValidator;

/**
 * Modifies container services for Automatic Updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class AutomaticUpdatesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_id = PackageManagerPhpExtensionsValidator::class;

    if ($container->hasDefinition($service_id)) {
      $container->getDefinition($service_id)
        ->setClass(PhpExtensionsValidator::class);
    }
  }

}
