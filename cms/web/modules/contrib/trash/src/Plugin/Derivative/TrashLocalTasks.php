<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for trash-enabled entity types.
 */
class TrashLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('trash.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if ($this->configFactory->get('trash.settings')->get('compact_overview')) {
      return ['trash' => $base_plugin_definition];
    }

    $this->derivatives = [];
    $enabled_entity_types = $this->trashManager->getEnabledEntityTypes();

    if (!$enabled_entity_types) {
      return $this->derivatives;
    }

    $default_entity_type = in_array('node', $enabled_entity_types, TRUE) ? 'node' : reset($enabled_entity_types);

    foreach ($enabled_entity_types as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      $this->derivatives[$entity_type_id] = $base_plugin_definition;
      $this->derivatives[$entity_type_id]['title'] = $entity_type->getCollectionLabel();
      $this->derivatives[$entity_type_id]['route_parameters'] = ['entity_type_id' => $entity_type_id];
      $this->derivatives[$entity_type_id]['cache_tags'] = ['config:trash.settings'];

      // Default task.
      if ($default_entity_type === $entity_type_id) {
        $this->derivatives[$entity_type_id]['route_name'] = $base_plugin_definition['parent_id'];
        // Emulate default logic because without the base plugin id we can't
        // change the base_route.
        $this->derivatives[$entity_type_id]['weight'] = -10;

        unset($this->derivatives[$entity_type_id]['route_parameters']);
      }
    }

    return $this->derivatives;
  }

}
