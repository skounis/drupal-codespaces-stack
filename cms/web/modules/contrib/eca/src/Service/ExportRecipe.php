<?php

namespace Drupal\eca\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Entity\Eca;

/**
 * Service provides export to recipe functionality for ECA models.
 */
class ExportRecipe {

  use StringTranslationTrait;

  public const DEFAULT_NAMESPACE = 'drupal-eca-recipe';
  public const DEFAULT_DESTINATION = 'temporary://recipe';

  /**
   * Constructs the recipe export service.
   *
   * @param \Drupal\Core\Config\ManagedStorage $configStorage
   *   The config storage.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\eca\Service\Modellers $modellerService
   *   The ECA modeller service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   */
  public function __construct(
    protected readonly ManagedStorage $configStorage,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly Modellers $modellerService,
    protected readonly Messenger $messenger,
  ) {}

  /**
   * Exports the given ECA model to a recipe.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA model.
   * @param string|null $name
   *   The name of the model.
   * @param string $namespace
   *   The namespace to use for composer.
   * @param string $destination
   *   The directory, where to store the recipe.
   */
  public function doExport(Eca $eca, ?string $name = NULL, string $namespace = self::DEFAULT_NAMESPACE, string $destination = self::DEFAULT_DESTINATION): void {
    $destination = rtrim($destination, '/');
    $configDestination = $destination . '/config';
    $composerJson = $destination . '/composer.json';
    $recipeYml = $destination . '/recipe.yml';
    $readmeMd = $destination . '/README.md';
    if (file_exists($configDestination) && !$this->fileSystem->deleteRecursive($configDestination)) {
      $this->messenger->addError($this->t('A config directory already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($composerJson) && !$this->fileSystem->unlink($composerJson)) {
      $this->messenger->addError($this->t('A composer.json already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($recipeYml) && !$this->fileSystem->unlink($recipeYml)) {
      $this->messenger->addError($this->t('A recipe.yml already exists in the given destination and can not be removed.'));
      return;
    }
    if (file_exists($readmeMd) && !$this->fileSystem->unlink($readmeMd)) {
      $this->messenger->addError($this->t('A README.md already exists in the given destination and can not be removed.'));
      return;
    }
    if (!$this->fileSystem->mkdir($configDestination, FileSystem::CHMOD_DIRECTORY, TRUE)) {
      $this->messenger->addError($this->t('The destination does not exist or is not writable.'));
      return;
    }
    if (!is_writable($configDestination)) {
      $this->messenger->addError($this->t('The destination is not writable.'));
      return;
    }
    $this->fileSystem->prepareDirectory($destination);
    $this->fileSystem->prepareDirectory($configDestination);

    if ($name === NULL) {
      $name = $this->defaultName($eca);
    }
    $description = $eca->getModel()->getDocumentation();
    $dependencies = [
      'config' => [
        'eca.eca.' . $eca->id(),
      ],
      'module' => [],
    ];
    $comesWithEcaModel = $this->configStorage->read('eca.model.' . $eca->id());
    if ($comesWithEcaModel) {
      $dependencies['config'][] = 'eca.model.' . $eca->id();
    }
    $this->modellerService->getNestedDependencies($dependencies, $eca->getDependencies());

    $actions = [];
    $imports = [];
    foreach ($dependencies['config'] as $configName) {
      $config = $this->configStorage->read($configName);
      if (!$config) {
        continue;
      }
      unset($config['uuid'], $config['_core']);
      if (str_starts_with($configName, 'user.role.')) {
        $actions[$configName] = [
          'ensure_exists' => [
            'label' => $config['label'],
          ],
          'grantPermissions' => $config['permissions'],
        ];
      }
      else {
        $canBeImported = FALSE;
        foreach ($config['dependencies']['module'] ?? [] as $module) {
          if ($this->isProvidedByModule($module, $configName)) {
            $imports[$module][] = $configName;
            $canBeImported = TRUE;
            break;
          }
        }
        if (!$canBeImported) {
          $this->fileSystem->saveData(Yaml::encode($config), $configDestination . '/' . $configName . '.yml', FileExists::Replace);
        }
      }
    }

    $this->fileSystem->saveData(json_encode($this->getComposer($eca->id(), $namespace, $name, $dependencies['module']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . PHP_EOL, $composerJson, FileExists::Replace);
    $this->fileSystem->saveData(Yaml::encode($this->getRecipe($name, $description, $dependencies['module'], $actions, $imports)), $recipeYml, FileExists::Replace);
    $this->fileSystem->saveData($this->getReadme($eca->id(), $name, $description, $namespace), $readmeMd, FileExists::Replace);
  }

  /**
   * Gets the default name for the recipe.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA model.
   *
   * @return string
   *   The default name for the recipe.
   */
  public function defaultName(Eca $eca): string {
    return (string) $eca->label();
  }

  /**
   * Helper function to determine if a config name is provided by given module.
   *
   * @param string $module
   *   The module.
   * @param string $configName
   *   The config name.
   *
   * @return bool
   *   TRUE, if that module provides that config, FALSE otherwise.
   */
  private function isProvidedByModule(string $module, string $configName): bool {
    $pathname = $this->fileSystem->dirName($this->moduleExtensionList->getPathname($module));
    foreach (['install', 'optional'] as $item) {
      if (file_exists($pathname . '/config/' . $item . '/' . $configName . '.yml')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds the content of the composer.json file.
   *
   * @param string $id
   *   The recipe ID.
   * @param string $namespace
   *   The namespace.
   * @param string $description
   *   The recipe description.
   * @param array $modules
   *   The list of required module names.
   *
   * @return string[]
   *   The content of the composer.json file as an array.
   */
  protected function getComposer(string $id, string $namespace, string $description, array $modules = []): array {
    $composer = [
      'name' => $namespace . '/' . $id,
      'type' => 'drupal-recipe',
      'description' => $description,
      'license' => 'GPL-2.0-or-later',
    ];
    if ($modules) {
      $composer['require'] = [
        'drupal/core' => '>=10.3',
      ];
      $list = $this->moduleExtensionList->getList();
      foreach ($modules as $module) {
        $path = $this->moduleExtensionList->getPath($module);
        if (!str_starts_with($path, 'core/modules')) {
          foreach ($list[$module]->requires ?? [] as $key => $dependency) {
            if (str_starts_with($path, $this->moduleExtensionList->getPath($key) . '/')) {
              $module = $key;
              break;
            }
          }
          $composer['require']['drupal/' . $module] = '*';
        }
      }
    }
    return $composer;
  }

  /**
   * Builds the content of the recipe file.
   *
   * @param string $name
   *   The recipe name.
   * @param string $description
   *   The recipe description.
   * @param array $modules
   *   The list of required modules.
   * @param array $actions
   *   The list of config actions.
   * @param array $imports
   *   The list of config imports keyed by module name.
   *
   * @return string[]
   *   The content of the recipe file as an array.
   */
  protected function getRecipe(string $name, string $description, array $modules = [], array $actions = [], array $imports = []): array {
    $recipe = [
      'name' => $name,
      'description' => $description,
      'type' => 'ECA',
    ];
    if ($modules) {
      $recipe['install'] = $modules;
    }
    if ($actions) {
      $recipe['config']['actions'] = $actions;
    }
    if ($imports) {
      $recipe['config']['import'] = $imports;
    }
    return $recipe;
  }

  /**
   * Builds the content of the readme file.
   *
   * @param string $id
   *   The ID of the recipe.
   * @param string $name
   *   The recipe name.
   * @param string $description
   *   The recipe description.
   * @param string $namespace
   *   The namespace.
   *
   * @return string
   *   The content of the readme file.
   */
  protected function getReadme(string $id, string $name, string $description, string $namespace): string {
    $description = str_replace(['](/', '.md)'], [
      '](https://ecaguide.org/',
      ')',
    ], $description);
    return <<<end_of_readme
## ECA Recipe: $name

ID: $id

$description

### Installation

```shell
composer require $namespace/$id
cd web && php core/scripts/drupal recipe ../vendor/$namespace/$id
```
end_of_readme;
  }

}
