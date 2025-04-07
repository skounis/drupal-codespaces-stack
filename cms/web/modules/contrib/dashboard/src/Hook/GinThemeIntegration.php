<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Gin theme integration hooks.
 */
class GinThemeIntegration {

  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * If the admin theme is gin, we add extra css that uses gin styling.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): array {
    if ($extension === 'dashboard' && $this->configFactory->get('system.theme')->get('admin') === 'gin') {
      $libraries['dashboard']['css']['theme'] += ['css/dashboard.gin.css' => []];
    }
    return $libraries;
  }

}
