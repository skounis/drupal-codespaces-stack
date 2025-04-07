<?php

namespace Drupal\eca\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\ErrorHandlerTrait;
use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\PluginManager\Modeller;

/**
 * Service class for ECA modellers.
 */
class Modellers {

  use ErrorHandlerTrait;
  use ServiceTrait;

  /**
   * ECA config entity storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $configStorage;

  /**
   * ECA model storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $modelStorage;

  /**
   * ECA modeller plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Modeller
   */
  protected Modeller $pluginManagerModeller;

  /**
   * ECA event plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected Event $pluginManagerEvent;

  /**
   * ECA action services.
   *
   * @var \Drupal\eca\Service\Actions
   */
  protected Actions $actionServices;

  /**
   * ECA condition services.
   *
   * @var \Drupal\eca\Service\Conditions
   */
  protected Conditions $conditionServices;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Export storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $exportStorage;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Modellers constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\eca\PluginManager\Modeller $plugin_manager_modeller
   *   The ECA modeller plugin manager.
   * @param \Drupal\eca\PluginManager\Event $plugin_manager_event
   *   The ECA event plugin manager.
   * @param \Drupal\eca\Service\Actions $action_services
   *   The ECA action service.
   * @param \Drupal\eca\Service\Conditions $condition_services
   *   The ECA condition service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\StorageInterface $export_storage
   *   The export storage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Modeller $plugin_manager_modeller, Event $plugin_manager_event, Actions $action_services, Conditions $condition_services, LoggerChannelInterface $logger, FileSystemInterface $file_system, StorageInterface $export_storage, ConfigFactoryInterface $config_factory) {
    $this->configStorage = $entity_type_manager->getStorage('eca');
    $this->modelStorage = $entity_type_manager->getStorage('eca_model');
    $this->pluginManagerModeller = $plugin_manager_modeller;
    $this->pluginManagerEvent = $plugin_manager_event;
    $this->actionServices = $action_services;
    $this->conditionServices = $condition_services;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
    $this->exportStorage = $export_storage;
    $this->configFactory = $config_factory;
  }

  /**
   * Loads the given Eca config entity by its ID.
   *
   * @param string $id
   *   The ID of an ECA model.
   *
   * @return \Drupal\eca\Entity\Eca|null
   *   The Eca config entity if available, NULL otherwise.
   */
  public function loadModel(string $id): ?Eca {
    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $this->configStorage->load(mb_strtolower($id));
    return $eca;
  }

  /**
   * Save a model as config.
   *
   * @param \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface $modeller
   *   The modeller controlling the ECA config entity.
   *
   * @return bool
   *   Returns TRUE, if a reload of the saved model is required. That's the case
   *   when this is either a new model or if the label had changed. It returns
   *   FALSE otherwise, if none of those conditions applies.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \LogicException
   */
  public function saveModel(ModellerInterface $modeller): bool {
    $id = mb_strtolower($modeller->getId());
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->configStorage->load($id);
    if ($config === NULL) {
      /**
       * @var \Drupal\eca\Entity\Eca $config
       */
      $config = $this->configStorage->create([
        'id' => $id,
        'modeller' => $modeller->getPluginId(),
      ]);
      $requiresReload = TRUE;
    }
    else {
      $requiresReload = $config->label() !== $modeller->getLabel();
    }
    $config
      ->set('label', $modeller->getLabel())
      ->set('status', $modeller->getStatus())
      ->set('version', $modeller->getVersion())
      ->set('events', [])
      ->set('conditions', [])
      ->set('actions', []);
    $modeller->readComponents($config);
    if ($modeller->hasError()) {
      // If the model contains error(s), don't save it and do not ask for a
      // page reload, because that would cause data loss.
      return FALSE;
    }
    // Only save model if reading its components succeeded without errors.
    $config->save();
    $config->getModel()
      ->setData($modeller)
      ->save();
    return $requiresReload;
  }

  /**
   * Gets a list of all available modeller plugin definitions.
   *
   * @return array
   *   The list of modeller plugin definitions indexed by their ID.
   */
  public function getModellerDefinitions(): array {
    return $this->pluginManagerModeller->getDefinitions();
  }

  /**
   * Returns an instance of the modeller for the given id.
   *
   * @param string $plugin_id
   *   The id of the modeller plugin.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface|null
   *   The modeller instance, or NULL if the plugin doesn't exist.
   */
  public function getModeller(string $plugin_id): ?ModellerInterface {
    try {
      /**
       * @var \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface $modeller
       */
      $modeller = $this->pluginManagerModeller->createInstance($plugin_id);
    }
    catch (PluginException) {
      return NULL;
    }
    return $modeller;
  }

  /**
   * Returns a sorted list of event plugins.
   *
   * @return \Drupal\eca\Plugin\ECA\Event\EventInterface[]
   *   The sorted list of events.
   */
  public function events(): array {
    static $events;
    if ($events === NULL) {
      $this->enableExtendedErrorHandling('Collecting all available events');
      $events = [];
      foreach ($this->pluginManagerEvent->getDefinitions() as $plugin_id => $definition) {
        try {
          /** @var \Drupal\eca\Plugin\ECA\Event\EventInterface $plugin */
          $plugin = $this->pluginManagerEvent->createInstance($plugin_id);
          $events[] = $plugin;
        }
        catch (PluginException | \Throwable) {
          // Can be ignored.
        }
      }
      $this->resetExtendedErrorHandling();
      $this->sortPlugins($events);
    }
    return $events;
  }

  /**
   * Export components for all ECA modellers.
   */
  public function exportTemplates(): void {
    foreach ($this->pluginManagerModeller->getDefinitions() as $plugin_id => $definition) {
      try {
        /**
         * @var \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface $modeller
         */
        $modeller = $this->pluginManagerModeller->createInstance($plugin_id);
        $modeller->exportTemplates();
      }
      catch (PluginException) {
        // Can be ignored.
      }
    }
  }

  /**
   * Exports the ECA config with all dependencies into an archive.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $archiveFileName
   *   The fully qualified filename for the archive.
   *
   * @return array
   *   An array with "config" and "module" keys, each containing the list of
   *   dependencies.
   */
  public function exportArchive(Eca $eca, string $archiveFileName): array {
    $dependencies = [
      'config' => [
        'eca.eca.' . $eca->id(),
      ],
      'module' => [],
    ];
    $comesWithEcaModel = $this->exportStorage->read('eca.model.' . $eca->id());
    if ($comesWithEcaModel) {
      $dependencies['config'][] = 'eca.model.' . $eca->id();
    }
    $this->getNestedDependencies($dependencies, $eca->getDependencies());
    if (file_exists($archiveFileName)) {
      try {
        @$this->fileSystem->delete($archiveFileName);
      }
      catch (FileException) {
        // Ignore failed deletes.
      }
    }
    $archiver = new ArchiveTar($archiveFileName, 'gz');
    foreach ($dependencies['config'] as $name) {
      $config = $this->exportStorage->read($name);
      if ($config) {
        unset($config['uuid'], $config['_core']);
        $archiver->addString("$name.yml", Yaml::encode($config));
      }
    }
    $archiver->addString('dependencies.yml', Yaml::encode($dependencies));

    // Remove "'eca.eca.ID" from the config dependencies.
    array_shift($dependencies['config']);
    if ($comesWithEcaModel) {
      // Also remove "'eca.model.ID" from the config dependencies.
      array_shift($dependencies['config']);
    }
    foreach ($dependencies as $type => $values) {
      if (empty($values)) {
        unset($dependencies[$type]);
      }
      else {
        sort($dependencies[$type]);
      }
    }
    return $dependencies;
  }

  /**
   * Recursively determines config dependencies.
   *
   * @param array $allDependencies
   *   The list of all dependencies.
   * @param array $dependencies
   *   The list of dependencies to be added.
   */
  public function getNestedDependencies(array &$allDependencies, array $dependencies): void {
    foreach ($dependencies['module'] ?? [] as $module) {
      if (!in_array($module, $allDependencies['module'], TRUE)) {
        $allDependencies['module'][] = $module;
      }
    }
    if (empty($dependencies['config'])) {
      return;
    }
    foreach ($dependencies['config'] as $dependency) {
      if (!in_array($dependency, $allDependencies['config'], TRUE)) {
        $allDependencies['config'][] = $dependency;
        $depConfig = $this->configFactory->get($dependency)->getStorage()->read($dependency);
        if ($depConfig && isset($depConfig['dependencies'])) {
          $this->getNestedDependencies($allDependencies, $depConfig['dependencies']);
        }
      }
    }
  }

}
