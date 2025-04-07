<?php

namespace Drupal\scheduler;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\scheduler\Annotation\SchedulerPlugin;

/**
 * Provides a Scheduler Plugin Manager.
 *
 * @package Drupal\scheduler
 */
class SchedulerPluginManager extends DefaultPluginManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SchedulerPluginManager object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $module_handler,
    // Trailing comma is incompatible with PHPUnit 9.6.19 in Drupal 9.5 PHP 7.4.
    // phpcs:ignore Drupal.Functions.MultiLineFunctionDeclaration.MissingTrailingComma
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $subdir = 'Plugin/Scheduler';
    $plugin_interface = SchedulerPluginInterface::class;
    $plugin_definition_annotation_name = SchedulerPlugin::class;

    parent::__construct(
      $subdir,
      $namespaces,
      $module_handler,
      $plugin_interface,
      $plugin_definition_annotation_name
    );

    $this->alterInfo('scheduler_info');
    $this->setCacheBackend($cacheBackend, 'scheduler_info');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    // This is an overridden method from the parent class. This version filters
    // out plugins with missing module and entity type dependencies before they
    // are initialized, removing the need for separate checks per plugin.
    $definitions = parent::findDefinitions();

    foreach ($definitions as $plugin_id => $plugin_definition) {
      if (!empty($plugin_definition['dependency']) && !$this->moduleHandler->moduleExists($plugin_definition['dependency'])) {
        unset($definitions[$plugin_id]);
        continue;
      }

      $entityType = $this->entityTypeManager->getDefinition($plugin_definition['entityType'], FALSE);
      if (!$entityType || !$entityType->getBundleEntityType()) {
        unset($definitions[$plugin_id]);
      }
    }

    return $definitions;
  }

}
