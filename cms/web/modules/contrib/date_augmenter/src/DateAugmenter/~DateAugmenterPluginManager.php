<?php

namespace Drupal\date_augmenter\DateAugmenter;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\date_augmenter\Event\SearchApiEvents;
use Drupal\date_augmenter\SearchApiPluginManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages processor plugins.
 *
 * @see \Drupal\date_augmenter\Annotation\SearchApiDateAugmenter
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterInterface
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginBase
 * @see plugin_api
 */
class DateAugmenterPluginManager extends SearchApiPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs a DateAugmenterPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EventDispatcherInterface $eventDispatcher, TranslationInterface $translation) {
    parent::__construct('Plugin/date_augmenter/processor', $namespaces, $module_handler, $eventDispatcher, 'Drupal\date_augmenter\DateAugmenter\DateAugmenterInterface', 'Drupal\date_augmenter\Annotation\SearchApiDateAugmenter');

    $this->setCacheBackend($cache_backend, 'date_augmenter_processors');
    $this->alterInfo('date_augmenter_processor_info');
    $this->alterEvent(SearchApiEvents::GATHERING_PROCESSORS);
    $this->setStringTranslation($translation);
  }

  /**
   * Retrieves information about the available processing stages.
   *
   * These are then used by processors in their "stages" definition to specify
   * in which stages they will run.
   *
   * @return array
   *   An associative array mapping stage identifiers to information about that
   *   stage. The information itself is an associative array with the following
   *   keys:
   *   - label: The translated label for this stage.
   */
  public function getProcessingStages() {
    return [
      DateAugmenterInterface::STAGE_PREPROCESS_INDEX => [
        'label' => $this->t('Preprocess index'),
      ],
      DateAugmenterInterface::STAGE_PREPROCESS_QUERY => [
        'label' => $this->t('Preprocess query'),
      ],
      DateAugmenterInterface::STAGE_POSTPROCESS_QUERY => [
        'label' => $this->t('Postprocess query'),
      ],
    ];
  }

}
