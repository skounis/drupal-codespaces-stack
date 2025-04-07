<?php

namespace Drupal\eca\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Plugin\ECA\EcaPluginBase;

/**
 * Trait for ECA modeller, condition and action services.
 */
trait ServiceTrait {

  use StringTranslationTrait;

  /**
   * Helper function to sort plugins by their label.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The list of plugin to be sorted.
   */
  public function sortPlugins(array &$plugins): void {
    foreach ($plugins as $plugin) {
      $provider = $plugin->getPluginDefinition()['provider'] ?? 'eca';
      if (!isset(EcaPluginBase::$modules[$provider])) {
        EcaPluginBase::$modules[$provider] = \Drupal::service('extension.list.module')->getName($provider);
      }
    }
    usort($plugins, static function ($p1, $p2) {
      $m1 = EcaPluginBase::$modules[$p1->getPluginDefinition()['provider'] ?? 'eca'];
      $m2 = EcaPluginBase::$modules[$p2->getPluginDefinition()['provider'] ?? 'eca'];
      if ($m1 < $m2) {
        return -1;
      }
      if ($m1 > $m2) {
        return 1;
      }
      $l1 = (string) $p1->getPluginDefinition()['label'];
      $l2 = (string) $p2->getPluginDefinition()['label'];
      if ($l1 < $l2) {
        return -1;
      }
      if ($l1 > $l2) {
        return 1;
      }
      return 0;
    });
  }

  /**
   * Builds a field label from the key.
   *
   * @param string $key
   *   The key of the field from which to build a label.
   *
   * @return string
   *   The built label for the field identified by key.
   */
  public static function convertKeyToLabel(string $key): string {
    $labelParts = explode('_', $key);
    $labelParts[0] = ucfirst($labelParts[0]);
    return implode(' ', $labelParts);
  }

}
