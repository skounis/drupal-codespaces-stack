<?php

namespace Drupal\dashboard\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all dashboards.
 */
class DashboardLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Creates a DashboardLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $dashboards = $this->entityTypeManager->getStorage('dashboard')->loadMultiple();
    foreach ($dashboards as $dashboard) {
      $this->derivatives['dashboard.' . $dashboard->id()] = [
        'route_name' => 'entity.dashboard.canonical',
        'route_parameters' => [
          'dashboard' => $dashboard->id(),
        ],
        'weight' => $dashboard->getWeight(),
        'title' => $dashboard->label(),
        'base_route' => 'dashboard',
      ];
    }
    return $this->derivatives;
  }

}
