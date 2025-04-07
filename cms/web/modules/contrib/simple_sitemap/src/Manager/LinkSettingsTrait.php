<?php

namespace Drupal\simple_sitemap\Manager;

/**
 * Provides a helper for supplementing the link settings.
 */
trait LinkSettingsTrait {

  /**
   * Supplements all missing link setting with default values.
   *
   * @param array|null &$settings
   *   Link settings to supplement.
   * @param array $overrides
   *   Link settings overrides.
   */
  public static function supplementDefaultSettings(&$settings, array $overrides = []): void {
    foreach (self::$linkSettingDefaults as $setting => $value) {
      if (!isset($settings[$setting])) {
        $settings[$setting] = $overrides[$setting] ?? $value;
      }
    }
  }

}
