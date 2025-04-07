<?php

namespace Drupal\eca\Drush\Commands;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eca\EcaUpdate;
use Drupal\eca\Service\ExportRecipe;
use Drupal\eca\Service\Modellers;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA Drush command file.
 */
final class EcaCommands extends DrushCommands {

  /**
   * ECA config entity storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $configStorage;

  /**
   * Constructs an EcaCommands object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    private readonly Modellers $ecaServiceModeller,
    private readonly ExportRecipe $exportRecipe,
    private readonly EcaUpdate $ecaUpdate,
  ) {
    parent::__construct();
    $this->configStorage = $entityTypeManager->getStorage('eca');
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\eca\Drush\Commands\EcaCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): EcaCommands {
    return new EcaCommands(
      $container->get('entity_type.manager'),
      $container->get('eca.service.modeller'),
      $container->get('eca.export.recipe'),
      $container->get('eca.update'),
    );
  }

  /**
   * Import a single ECA file.
   */
  #[Command(name: 'eca:import', aliases: [])]
  #[Argument(name: 'pluginId', description: 'The id of the modeller plugin.')]
  #[Argument(name: 'filename', description: 'The file name to import, relative to the Drupal root or absolute.')]
  #[Usage(name: 'eca:import camunda mymodel.xml', description: 'Import a single ECA file.')]
  public function import(string $pluginId, string $filename): void {
    $modeller = $this->ecaServiceModeller->getModeller($pluginId);
    if ($modeller === NULL) {
      $this->io()->error('This modeller plugin does not exist.');
      return;
    }
    if (!file_exists($filename)) {
      $this->io()->error('This file does not exist.');
      return;
    }
    try {
      $modeller->save(file_get_contents($filename), $filename);
    }
    catch (\LogicException | EntityStorageException $e) {
      $this->io()->error($e->getMessage());
    }
  }

  /**
   * Update all previously imported ECA files.
   */
  #[Command(name: 'eca:reimport', aliases: [])]
  #[Usage(name: 'eca:reimport', description: 'Update all previously imported ECA files.')]
  public function reimportAll(): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->configStorage->loadMultiple() as $eca) {
      $modeller = $this->ecaServiceModeller->getModeller($eca->get('modeller'));
      if ($modeller === NULL) {
        $this->logger->error('This modeller plugin ' . $eca->get('modeller') . ' does not exist.');
        continue;
      }
      if ($modeller->isEditable()) {
        // Editable models have no external files.
        continue;
      }
      $model = $eca->getModel();
      $filename = $model->getFilename();
      if (!file_exists($filename)) {
        $this->logger->error('This file ' . $filename . ' does not exist.');
        continue;
      }
      try {
        $modeller->save(file_get_contents($filename), $filename);
      }
      catch (\LogicException | EntityStorageException $e) {
        $this->io()->error($e->getMessage());
      }
    }
  }

  /**
   * Export templates for all ECA modellers.
   */
  #[Command(name: 'eca:export:templates', aliases: [])]
  #[Usage(name: 'eca:export:templates', description: 'Export templates for all ECA modellers.')]
  public function exportTemplates(): void {
    foreach ($this->ecaServiceModeller->getModellerDefinitions() as $plugin_id => $definition) {
      $modeller = $this->ecaServiceModeller->getModeller($plugin_id);
      if ($modeller === NULL) {
        $this->io()->error('This modeller plugin does not exist.');
        continue;
      }
      $modeller->exportTemplates();
    }
  }

  /**
   * Updates all existing ECA entities calling ::updateModel in their modeller.
   *
   * It is the modeller's responsibility to load all existing plugins and find
   * out if the model data, which is proprietary to them, needs to be updated.
   */
  #[Command(name: 'eca:update', aliases: [])]
  #[Usage(name: 'eca:update', description: 'Update all models if plugins got changed.')]
  public function updateAllModels(): void {
    $this->ecaUpdate->updateAllModels();
    if ($infos = $this->ecaUpdate->getInfos()) {
      $this->io()->info(implode(PHP_EOL, $infos));
    }
    if ($errors = $this->ecaUpdate->getErrors()) {
      $this->io()->error(implode(PHP_EOL, $errors));
    }
  }

  /**
   * Disable all existing ECA entities.
   */
  #[Command(name: 'eca:disable', aliases: [])]
  #[Usage(name: 'eca:disable', description: 'Disable all models.')]
  public function disableAllModels(): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->configStorage->loadMultiple() as $eca) {
      $modeller = $this->ecaServiceModeller->getModeller($eca->get('modeller'));
      if ($modeller === NULL) {
        $this->logger->error('This modeller plugin ' . $eca->get('modeller') . ' does not exist.');
        continue;
      }
      $modeller
        ->setConfigEntity($eca)
        ->disable();
    }
  }

  /**
   * Enable all existing ECA entities.
   */
  #[Command(name: 'eca:enable', aliases: [])]
  #[Usage(name: 'eca:enable', description: 'Enable all models.')]
  public function enableAllModels(): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->configStorage->loadMultiple() as $eca) {
      $modeller = $this->ecaServiceModeller->getModeller($eca->get('modeller'));
      if ($modeller === NULL) {
        $this->logger->error('This modeller plugin ' . $eca->get('modeller') . ' does not exist.');
        continue;
      }
      $modeller
        ->setConfigEntity($eca)
        ->enable();
    }
  }

  /**
   * Rebuild the state of subscribed events.
   */
  #[Command(name: 'eca:subscriber:rebuild', aliases: [])]
  #[Usage(name: 'eca:subscriber:rebuild', description: 'Rebuild the state of subscribed events.')]
  public function rebuildSubscribedEvents(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->configStorage;
    $storage->rebuildSubscribedEvents();
  }

  /**
   * Export a model as a recipe.
   */
  #[Command(name: 'eca:model:export', aliases: [])]
  #[Argument(name: 'id', description: 'The ID of the model.')]
  #[Option(name: 'namespace', description: 'The namespace of the composer package.')]
  #[Option(name: 'destination', description: 'The directory where to store the recipe.')]
  #[Usage(name: 'eca:model:export MODELID', description: 'Export the model with the given ID as a recipe.')]
  #[Usage(name: 'eca:model:export MODELID --namespace=your-vendor', description: 'Customize the recipe namespace (name prefix in composer.json).')]
  #[Usage(name: 'eca:model:export MODELID --destination=../recipes/process_abc', description: 'Output the recipe at a custom relative path.')]
  public function exportModel(string $id, array $options = ['namespace' => self::OPT, 'destination' => self::OPT]): void {
    /** @var \Drupal\eca\Entity\Eca|null $eca */
    $eca = $this->configStorage->load($id);
    if ($eca === NULL) {
      $this->io()->error('The given ECA model does not exist!');
      return;
    }
    $namespace = $options['namespace'] ?? ExportRecipe::DEFAULT_NAMESPACE;
    $destination = $options['destination'] ?? ExportRecipe::DEFAULT_DESTINATION;
    $this->exportRecipe->doExport($eca, NULL, $namespace, $destination);
  }

}
