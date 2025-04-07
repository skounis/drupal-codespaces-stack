<?php

namespace Drupal\dashboard\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\dashboard\DashboardManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a dashboard navigation block class.
 *
 * @internal
 */
#[Block(
  id: "navigation_dashboard",
  admin_label: new TranslatableMarkup("Navigation Dashboard"),
)]
final class NavigationDashboardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new NavigationDashboardBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dashboard\DashboardManager $dashboardManager
   *   The dashboard manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected DashboardManager $dashboardManager) {
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
  public function build(): array {
    return [
      '#theme' => 'menu_region__dashboard',
      '#title' => $this->configuration['label'],
      '#url' => Url::fromRoute('dashboard')->toString(),
      '#attached' => [
        'library' => [
          'dashboard/dashboard.navigation',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) : AccessResultInterface {
    $default = $this->dashboardManager->getDefaultDashboard($account);
    return AccessResult::allowedIf($default !== NULL);
  }

}
