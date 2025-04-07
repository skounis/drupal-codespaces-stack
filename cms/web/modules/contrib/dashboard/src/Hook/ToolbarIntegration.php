<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Toolbar module integration hooks.
 */
class ToolbarIntegration {

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(&$items) {
    $items['administration']['#attached']['library'][] = 'dashboard/toolbar';
  }

}
