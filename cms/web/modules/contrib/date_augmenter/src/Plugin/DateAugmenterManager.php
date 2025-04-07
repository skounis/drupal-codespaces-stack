<?php

namespace Drupal\date_augmenter\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides the Date augmenter plugin manager.
 */
class DateAugmenterManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs a new DateAugmenterManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, TranslationInterface $stringTranslation) {
    parent::__construct('Plugin/DateAugmenter', $namespaces, $module_handler, 'Drupal\date_augmenter\DateAugmenter\DateAugmenterInterface', 'Drupal\date_augmenter\Annotation\DateAugmenter');

    $this->alterInfo('date_augmenter_plugin_info');
    $this->setCacheBackend($cache_backend, 'date_augmenter_plugins');
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Create an array of the enabled plugins.
   *
   * @param array|null $config
   *   The configuration of the date augmenters.
   *
   * @return array
   *   A keyed array of instantiated plugins.
   */
  public function getActivePlugins(?array $config) {
    if (!$config || empty($config['status'])) {
      return [];
    }
    // Considered starting with $config['weights] and then verifying each is
    // active with a status check, but this seems more fault tolerant.
    $active_augmenters = $config['status'] ?? [];
    $augmenter_weights = [];
    foreach ($active_augmenters as $augmenter_id => $active) {
      if ($active) {
        $augmenter_weights[$augmenter_id] = $config['weights']['order'][$augmenter_id]['weight'] ?? 0;
      }
    }
    asort($augmenter_weights);
    $plugins = [];
    foreach ($augmenter_weights as $augmenter_id => $weight) {
      $plugins[$augmenter_id] = $this->createInstance($augmenter_id);
    }
    return $plugins;
  }

  /**
   * Attempts to retrieve a setting normally stored as a ThirdPartySetting.
   *
   * @param mixed $entity
   *   The object used to retrieve the value.
   * @param string $property
   *   The property to retrieve.
   * @param mixed $default
   *   A value to return if no other approach is valid.
   *
   * @return mixed
   *   The retrieved value, or the default.
   */
  public static function getThirdPartyFallback($entity, $property, $default = NULL) {
    $value = $default;
    if (method_exists($entity, 'getThirdPartySetting')) {
      // Works for field definitions and rule objects.
      $value = $entity
        ->getThirdPartySetting('date_augmenter', $property, $default);
    }
    elseif (method_exists($entity, 'getSetting')) {
      // For custom entities, set value in your field definition.
      $value = $entity->getSetting($property);
    }
    return $value;
  }

}
