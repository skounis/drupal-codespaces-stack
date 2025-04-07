<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Navigation module integration hooks.
 */
class NavigationIntegration {

  public function __construct(protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * Hide dashboard blocks from the blocks UI, and mark our navigation as safe.
   *
   * @todo Revisit if https://www.drupal.org/project/drupal/issues/3443882 lands.
   */
  #[Hook('block_alter')]
  public function blockAlter(array &$definitions): void {
    $block_ids = [
      'dashboard_text_block',
      'dashboard_site_status',
      'navigation_dashboard',
    ];
    // Hide blocks from the blocks UI.
    foreach ($block_ids as $block_id) {
      if (isset($definitions[$block_id])) {
        $definitions[$block_id]['_block_ui_hidden'] = TRUE;
      }
    }

    // Allow the navigation dashboard block in navigation.
    if (isset($definitions['navigation_dashboard'])) {
      $definitions['navigation_dashboard']['allow_in_navigation'] = TRUE;
    }
  }

  /**
   * If navigation is present, we use our own navigation block.
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links): void {
    if ($this->moduleHandler->moduleExists('navigation')) {
      unset($links['system.dashboard']);
    }
  }

  /**
   * Navigation block theme hook.
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    $items['menu_region__dashboard'] = [
      'variables' => [
        'url' => [],
        'title' => NULL,
      ],
    ];
    return $items;
  }

}
