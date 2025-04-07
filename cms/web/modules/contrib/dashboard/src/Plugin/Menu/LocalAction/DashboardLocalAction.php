<?php

namespace Drupal\dashboard\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dashboard\DashboardInterface;
use Drupal\dashboard\DashboardManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Dashboard specific local action class.
 */
class DashboardLocalAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * The default dashboard for the current user, if available.
   *
   * @var \Drupal\dashboard\DashboardInterface|null
   */
  protected ?DashboardInterface $defaultDashboard;

  /**
   * Constructs a DashboardLocalAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\dashboard\DashboardManager $dashboardManager
   *   The dashboard manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteProviderInterface $route_provider,
    protected DashboardManager $dashboardManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('dashboard.manager')
    );
  }

  /**
   * Gets the default dashboard.
   *
   * @return \Drupal\dashboard\DashboardInterface|null
   *   The default dashboard for the current user, if available.
   */
  protected function defaultDashboard(): ?DashboardInterface {
    if (!isset($this->defaultDashboard)) {
      $this->defaultDashboard = $this->dashboardManager->getDefaultDashboard();
    }

    return $this->defaultDashboard;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = parent::getRouteParameters($route_match);

    // Retrieve the default dashboard for the main dashboard route.
    if ($route_match->getRouteName() === 'dashboard') {
      if ($this->defaultDashboard()) {
        $parameters['dashboard'] = $this->defaultDashboard()->id();
      }
    }

    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    if ($this->defaultDashboard()) {
      return $this->pluginDefinition['route_name'];
    }
    else {
      return 'entity.dashboard.collection';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    if ($this->defaultDashboard()) {
      return $this->pluginDefinition['title'];
    }
    else {
      return $this->t('Manage dashboards');
    }
  }

}
