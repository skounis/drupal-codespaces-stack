<?php

declare(strict_types=1);

namespace Drupal\dashboard\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dashboard\DashboardManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a local task plugin with a dynamic title for the default dashboard.
 */
class DashboardLocalTask extends LocalTaskDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DashboardManager $dashboardManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dashboard.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    return $this->dashboardManager->getDefaultDashboard()?->label() ?? parent::getTitle($request);
  }

}
