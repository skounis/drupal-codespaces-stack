<?php

declare(strict_types=1);

namespace Drupal\trash\Handler;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a compiler pass to register and configure trash handlers.
 */
class TrashHandlerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // The config factory might not be ready yet, so we bypass the container by
    // using the bootstrap factory.
    $config_storage = BootstrapConfigStorageFactory::get();
    // This config ignores overrides, so trash config from settings.php won't be
    // taken into account.
    /** @var \Drupal\Core\Config\ImmutableConfig $trash_settings */
    $trash_settings = $config_storage->read('trash.settings');

    $enabled_entity_types = $trash_settings['enabled_entity_types'] ?? [];
    $trash_handlers = $container->findTaggedServiceIds('trash_handler');

    foreach ($trash_handlers as $id => $attributes) {
      $entity_type_id = $attributes[0]['entity_type_id'];

      // Remove trash handlers for entity types that aren't enabled.
      if (!isset($enabled_entity_types[$entity_type_id])) {
        $container->removeDefinition($id);
      }
      else {
        // Keep track of entity types without a dedicated trash handler so we
        // can create one for them automatically.
        unset($enabled_entity_types[$entity_type_id]);

        $container->getDefinition($id)
          ->addMethodCall('setEntityTypeId', [$entity_type_id])
          ->setConfigurator(new Reference('trash.handler_configurator'));
      }
    }

    // Register a trash handler for entity types without a dedicated one.
    foreach (array_keys($enabled_entity_types) as $entity_type_id) {
      $container->register('trash.default_handler.' . $entity_type_id, DefaultTrashHandler::class)
        ->addTag('trash_handler', ['entity_type_id' => $entity_type_id])
        ->addMethodCall('setEntityTypeId', [$entity_type_id])
        ->setConfigurator(new Reference('trash.handler_configurator'));
    }
  }

}
