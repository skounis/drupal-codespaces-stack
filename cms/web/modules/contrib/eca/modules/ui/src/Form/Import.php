<?php

namespace Drupal\eca_ui\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Service\Modellers;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Import a model from a previous export.
 */
class Import extends FormBase {

  /**
   * ECA modeller service.
   *
   * @var \Drupal\eca\Service\Modellers
   */
  protected Modellers $modellerService;

  /**
   * Symfony request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Config manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected ConfigManagerInterface $configManager;

  /**
   * Cached storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected CachedStorage $configStorage;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $configCache;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManager
   */
  protected TypedConfigManager $configTyped;

  /**
   * Module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected ModuleInstallerInterface $moduleInstaller;

  /**
   * Theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected ThemeHandler $themeHandler;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The theme extension list.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected ThemeExtensionList $themeExtensionList;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Import {
    $form = parent::create($container);
    $form->modellerService = $container->get('eca.service.modeller');
    $form->request = $container->get('request_stack')->getCurrentRequest();
    $form->configManager = $container->get('config.manager');
    $form->configStorage = $container->get('config.storage');
    $form->configCache = $container->get('cache.config');
    $form->moduleHandler = $container->get('module_handler');
    $form->eventDispatcher = $container->get('event_dispatcher');
    $form->lock = $container->get('lock');
    $form->configTyped = $container->get('config.typed');
    $form->moduleInstaller = $container->get('module_installer');
    $form->themeHandler = $container->get('theme_handler');
    $form->moduleExtensionList = $container->get('extension.list.module');
    $form->themeExtensionList = $container->get('extension.list.theme');
    $form->fileSystem = $container->get('file_system');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eca_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($this->moduleHandler->moduleExists('config')) {
      $form['model'] = [
        '#type' => 'file',
        '#title' => $this->t('File containing the exported XML model or archive containing all dependent config entities.'),
      ];
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
      ];
    }
    else {
      $form['info'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Import requires the config module to be enabled.'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $all_files = $this->request->files->get('files', []);
    if (empty($all_files)) {
      $form_state->setErrorByName('model', 'No file provided.');
      return;
    }
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|bool $file */
    $file = reset($all_files);
    if (!$file) {
      $form_state->setErrorByName('model', 'No file provided.');
      return;
    }
    $filename = $file->getRealPath();
    if (!file_exists($filename)) {
      $form_state->setErrorByName('model', 'Something went wrong during upload.');
      return;
    }
    $extension = $file->getClientOriginalExtension();
    if ($extension === 'xml') {
      [$name] = explode('.', $file->getClientOriginalName());
      [$modellerId, $id] = explode('-', $name);
      $modeller = $this->modellerService->getModeller($modellerId);
      if (!$modeller) {
        $form_state->setErrorByName('model', 'The required modeller is not available.');
        return;
      }

      // Verify, that ID is not used yet.
      $data = file_get_contents($filename);
      $modeller->setModeldata($data);
      if ($eca = $this->modellerService->loadModel($modeller->getId())) {
        $form_state->setErrorByName('model', 'Model with that ID already available with the name "' . $eca->label() . '". Delete that first, when you really want to import this file.');
      }

      // Test import the model and display missing dependencies if it fails.
      try {
        $eca = $modeller->createNewModel($modeller->getId(), $data);
      }
      catch (\LogicException | EntityStorageException $e) {
        $this->messenger->addError($e->getMessage());
        return;
      }
      $modeller->readComponents($eca);
      if ($modeller->hasError()) {
        // Reading components didn't succeed and recognized errors got pushed to
        // Drupal's messenger.
        return;
      }
      try {
        $eca->calculateDependencies();
      }
      catch (\LogicException | InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $form_state->setErrorByName('model', $e->getMessage());
        return;
      }

      // Prepare for final import in submit handler.
      $form_state->setValue('model', [
        'filename' => $filename,
        'modellerId' => $modellerId,
        'id' => $id,
      ]);
    }
    elseif ($extension === 'gz') {
      $source_storage_dir = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'eca-import');
      unlink($source_storage_dir);
      $this->fileSystem->prepareDirectory($source_storage_dir);
      try {
        $archiver = new ArchiveTar($filename, 'gz');
        $files = [];
        foreach ($archiver->listContent() as $file) {
          $files[] = $file['filename'];
        }
        $archiver->extractList($files, $source_storage_dir, '', FALSE, FALSE);
        $this->fileSystem->unlink($filename);

        $dependencyFilename = $source_storage_dir . '/dependencies.yml';
        if (!file_exists($dependencyFilename)) {
          $form_state->setErrorByName('model', 'Uploaded archive is not consistent.');
        }
        else {
          $dependencies = Yaml::decode(file_get_contents($dependencyFilename));
          $this->fileSystem->unlink($dependencyFilename);
          $missingModules = [];
          if (isset($dependencies['module']) && is_array($dependencies['module'])) {
            foreach ($dependencies['module'] as $module) {
              if (!$this->moduleHandler->moduleExists($module)) {
                $missingModules[] = $module;
              }
            }
          }
          if (!empty($missingModules)) {
            $form_state->setErrorByName('model', 'Can not import archive due to missing module(s): ' . implode(', ', $missingModules));
          }
          elseif (empty($dependencies['config']) || !is_array($dependencies['config'])) {
            $form_state->setErrorByName('model', 'Archive is not consistent.');
          }
          else {
            $missingFiles = [];
            foreach ($dependencies['config'] as $item) {
              if (!file_exists($source_storage_dir . '/' . $item . '.yml')) {
                $missingFiles[] = $item;
              }
            }
            if (!empty($missingFiles)) {
              $form_state->setErrorByName('model', 'Can not import archive due to missing config file(s): ' . implode(', ', $missingFiles));
            }
            else {
              // Prepare for final import in submit handler.
              $form_state->setValue('model', $source_storage_dir);
            }
          }
        }
      }
      catch (\Exception $e) {
        $form_state->setErrorByName('model', $e->getMessage());
      }
    }
    else {
      $form_state->setErrorByName('model', 'Unsupported file extension.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $model = $form_state->getValue('model');
    if (is_array($model)) {
      // Import a single XML file.
      if ($modeller = $this->modellerService->getModeller($model['modellerId'])) {
        try {
          $modeller->save(file_get_contents($model['filename']), NULL, FALSE);
        }
        catch (\LogicException | EntityStorageException $e) {
          $this->messenger()->addError($e->getMessage());
        }
      }
    }
    else {
      // Import all files from an extracted archive.
      $source_storage_dir = $model;
      $source_storage = new FileStorage($source_storage_dir);
      $active_storage = $this->configStorage;
      $replacement_storage = new StorageReplaceDataWrapper($active_storage);
      $id = NULL;
      foreach ($source_storage->listAll() as $name) {
        $data = $source_storage->read($name);
        if (is_array($data)) {
          if (mb_strpos($name, 'eca.eca.') === 0) {
            $id = $data['id'];
          }
          $replacement_storage->replaceData($name, $data);
        }
        else {
          $this->messenger()->addError($this->t('The contained config entity %name is invalid and got ignored.', [
            '%name' => $name,
          ]));
        }
      }
      $source_storage = $replacement_storage;

      $storage_comparer = new StorageComparer($source_storage, $active_storage);
      if ($id === NULL) {
        $this->messenger()->addError('This file does not contain any ECA model.');
      }
      elseif (!$storage_comparer->createChangelist()->hasChanges()) {
        $this->messenger()->addStatus('There are no changes to import.');
      }
      else {
        $config_importer = new ConfigImporter(
          $storage_comparer,
          $this->eventDispatcher,
          $this->configManager,
          $this->lock,
          $this->configTyped,
          $this->moduleHandler,
          $this->moduleInstaller,
          $this->themeHandler,
          $this->getStringTranslation(),
          $this->moduleExtensionList,
          $this->themeExtensionList
        );
        if ($config_importer->alreadyImporting()) {
          $this->messenger()->addWarning('Another request may be synchronizing configuration already.');
        }
        else {
          try {
            $config_importer->import();
            if ($config_importer->getErrors()) {
              $this->messenger()->addError(implode("\n", $config_importer->getErrors()));
            }
            elseif ($eca = Eca::load($id)) {
              if ($eca->isEditable()) {
                $this->messenger()->addStatus($this->t('The configuration <a href="@link">@name</a> was imported successfully.', [
                  '@name' => $eca->label(),
                  '@link' => $eca->toUrl()->toString(),
                ]));
              }
              else {
                $this->messenger()->addStatus($this->t('The configuration %name was imported successfully.', [
                  '%name' => $eca->label(),
                ]));
              }
            }
            else {
              $this->messenger()->addError('Unexpected error.');
            }
          }
          catch (ConfigException $e) {
            $message = 'The import failed due to the following reason: ' . $e->getMessage() . "\n" . implode("\n", $config_importer->getErrors());
            $this->messenger()->addError($message);
          }
        }
      }
      $this->fileSystem->deleteRecursive($source_storage_dir);
    }
    $form_state->setRedirect('entity.eca.collection');
  }

}
