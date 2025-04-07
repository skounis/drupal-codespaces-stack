<?php

namespace Drupal\eca_development\Drush\Commands;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\ExportRecipe;
use Drupal\eca\Service\Modellers;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader as TwigLoader;

/**
 * ECA documentation Drush command file.
 */
final class DocsCommands extends DrushCommands {

  /**
   * Table of contents.
   *
   * @var array
   */
  protected array $toc = [];

  /**
   * List of all processed modules.
   *
   * @var array
   */
  protected array $modules = [];

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * ECA Action service.
   *
   * @var \Drupal\eca\Service\Actions
   */
  protected Actions $actionServices;

  /**
   * ECA Condition service.
   *
   * @var \Drupal\eca\Service\Conditions
   */
  protected Conditions $conditionServices;

  /**
   * ECA Modeller service.
   *
   * @var \Drupal\eca\Service\Modellers
   */
  protected Modellers $modellerServices;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Module Extension List.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * List of extensions.
   *
   * @var \Drupal\Core\Extension\Extension[]
   */
  protected ?array $moduleExtensions;

  /**
   * Twig array loader.
   *
   * @var \Twig\Loader\ArrayLoader
   */
  protected TwigLoader $twigLoader;

  /**
   * Twig environment service.
   *
   * @var \Twig\Environment
   */
  protected TwigEnvironment $twigEnvironment;

  /**
   * The export as recipe service.
   *
   * @var \Drupal\eca\Service\ExportRecipe
   */
  protected ExportRecipe $exportRecipe;

  /**
   * DocsCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\eca\Service\Actions $actionServices
   *   The ECA action services.
   * @param \Drupal\eca\Service\Conditions $conditionServices
   *   The ECA condition services.
   * @param \Drupal\eca\Service\Modellers $modellerServices
   *   The ECA modeller services.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\eca\Service\ExportRecipe $exportRecipe
   *   The export as recipe service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Actions $actionServices, Conditions $conditionServices, Modellers $modellerServices, FileSystemInterface $fileSystem, ModuleHandlerInterface $moduleHandler, ModuleExtensionList $moduleExtensionList, ExportRecipe $exportRecipe) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->actionServices = $actionServices;
    $this->conditionServices = $conditionServices;
    $this->modellerServices = $modellerServices;
    $this->fileSystem = $fileSystem;
    $this->moduleHandler = $moduleHandler;
    $this->twigLoader = new TwigLoader();
    $this->twigEnvironment = new TwigEnvironment($this->twigLoader);
    $this->moduleExtensionList = $moduleExtensionList;
    $this->moduleExtensions = $moduleExtensionList->getList();
    $this->exportRecipe = $exportRecipe;
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\eca_development\Drush\Commands\DocsCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): DocsCommands {
    return new DocsCommands(
      $container->get('entity_type.manager'),
      $container->get('eca.service.action'),
      $container->get('eca.service.condition'),
      $container->get('eca.service.modeller'),
      $container->get('file_system'),
      $container->get('module_handler'),
      $container->get('extension.list.module'),
      $container->get('eca.export.recipe'),
    );
  }

  /**
   * Export documentation for all plugins.
   */
  #[Command(name: 'eca:doc:plugins', aliases: [])]
  #[Usage(name: 'eca:doc:plugins', description: 'Export documentation for all plugins.')]
  public function plugins(): void {
    @$this->fileSystem->mkdir('../mkdocs/include/modules', NULL, TRUE);
    @$this->fileSystem->mkdir('../mkdocs/include/plugins', NULL, TRUE);
    $this->toc['0-ECA']['0-placeholder'] = 'plugins/eca/index.md';

    foreach ($this->modellerServices->events() as $event) {
      $this->pluginDoc($event);
    }
    foreach ($this->conditionServices->conditions() as $condition) {
      $this->pluginDoc($condition);
    }
    foreach ($this->actionServices->actions() as $action) {
      $this->pluginDoc($action);
    }
    $this->updateToc('plugins');
  }

  /**
   * Export documentation for all models.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  #[Command(name: 'eca:doc:models', aliases: [])]
  #[Usage(name: 'eca:doc:models', description: 'Export documentation for all models.')]
  public function models(): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->entityTypeManager
      ->getStorage('eca')
      ->loadMultiple() as $eca) {
      $this->modelDoc($eca);
      $this->exportRecipe->doExport($eca, $this->exportRecipe->defaultName($eca), ExportRecipe::DEFAULT_NAMESPACE, '../recipes/' . $eca->id());
    }
    $this->updateToc('library');
  }

  /**
   * Update the TOC file identified by $key.
   *
   * @param string $key
   *   The key for the TOC to update.
   */
  private function updateToc(string $key): void {
    $filename = '../mkdocs/toc/' . $key . '.yml';
    // @todo Merge with potentially existing TOC,
    $this->sortNestedArrayAssoc($this->toc);
    $content = Yaml::encode($this->toc);
    $content = '- ' . $key . '/index.md' . PHP_EOL . str_replace(
      ['0-ECA:', '  0-placeholder: ', '  1-', '  2-', '  3-'],
      ['ECA:', '  ', '  ', '  ', '  '],
      $content);
    $content = preg_replace_callback('/\n\s*/', static function (array $matches) {
      return $matches[0] . '- ';
    }, $content);
    file_put_contents($filename, substr($content, 0, -2));
  }

  /**
   * Sort array by key recursively.
   *
   * @param mixed $a
   *   The array to sort by key.
   */
  private function sortNestedArrayAssoc(mixed &$a): void {
    if (!is_array($a)) {
      return;
    }
    ksort($a);
    foreach ($a as $k => $v) {
      $this->sortNestedArrayAssoc($a[$k]);
    }
  }

  /**
   * Prepare documentation for given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The ECA plugin for which documentation should be created.
   */
  private function pluginDoc(PluginInspectionInterface $plugin): void {
    if (!empty($plugin->getPluginDefinition()['nodocs'])) {
      return;
    }
    $values = $this->getPluginValues($plugin);
    if ($values === NULL) {
      return;
    }
    $id = str_replace(':', '_', $plugin->getPluginId());
    $values['id_fs'] = $id;
    $this->modules[$values['provider']] = $values;

    $provider = $values['provider'];
    $values['extension_info'] = [
      'standalone' => TRUE,
      'module' => $provider,
    ];
    if (isset($this->moduleExtensions[$provider])) {
      // @phpstan-ignore-next-line
      if ($this->moduleExtensions[$provider]->origin === 'core') {
        $values['extension_info']['standalone'] = FALSE;
        $values['extension_info']['module'] = 'core';
      }
      else {
        // @phpstan-ignore-next-line
        $subpath = $this->moduleExtensions[$provider]->subpath;
        // @phpstan-ignore-next-line
        foreach ($this->moduleExtensions[$provider]->requires as $require) {
          // @phpstan-ignore-next-line
          if (isset($this->moduleExtensions[$require->getName()]) && str_contains($subpath, $this->moduleExtensions[$require->getName()]->subpath . '/')) {
            $values['extension_info']['standalone'] = FALSE;
            $values['extension_info']['module'] = $require->getName();
            break;
          }
        }
      }
    }

    $path = $values['path'];
    $filename = $path . '/' . $id . '.md';
    @$this->fileSystem->mkdir('../mkdocs/docs/' . $path, NULL, TRUE);
    file_put_contents('../mkdocs/docs/' . $filename, $this->render(__DIR__ . '/../../../templates/docs/plugin.md.twig', $values));

    $path = '../mkdocs/include/plugins/' . $values['provider'] . '/' . $values['type'] . '/';
    @$this->fileSystem->mkdir($path, NULL, TRUE);
    if (!file_exists($path . $id . '.md')) {
      file_put_contents($path . $id . '.md', '');
    }
    $path .= $id . '/';
    @$this->fileSystem->mkdir($path, NULL, TRUE);
    foreach ($values['fields'] as $field) {
      if (!file_exists($path . $field['name'] . '.md')) {
        file_put_contents($path . $field['name'] . '.md', '');
      }
    }

    if (!isset($values['toc'][$values['provider_name']])) {
      // Initialize TOC for a new provider.
      $values['toc'][$values['provider_name']]['0-placeholder'] = $values['provider_path'] . '/index.md';

      file_put_contents('../mkdocs/docs/' . $values['provider_path'] . '/index.md', $this->render(__DIR__ . '/../../../templates/docs/provider.md.twig', $values));
      if (!file_exists('../mkdocs/include/modules/' . $provider . '.md')) {
        file_put_contents('../mkdocs/include/modules/' . $provider . '.md', '');
      }
    }
    $values['toc'][$values['provider_name']][$values['weight'] . '-' . ucfirst($values['type']) . 's'][(string) $values['label']] = $filename;
  }

  /**
   * Extracts all required values from the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The ECA plugin for which values should be extracted.
   *
   * @return array|null
   *   The extracted values.
   */
  private function getPluginValues(PluginInspectionInterface $plugin): ?array {
    $values = $plugin->getPluginDefinition();
    if ($values['provider'] === 'core') {
      $values['provider_name'] = 'Drupal core';
    }
    else {
      $values['provider_name'] = $this->moduleExtensionList->getName($values['provider']);
    }
    if (str_starts_with($values['provider'], 'eca_')) {
      $basePath = str_replace('eca_', 'eca/', $values['provider']);
      $values['toc'] = &$this->toc['0-ECA'];
    }
    else {
      $basePath = $values['provider'];
      $values['toc'] = &$this->toc;
    }
    if (!isset($values['eca_version_introduced'])) {
      $values['eca_version_introduced'] = 'unknown';
    }
    $form_state = new FormState();
    if ($plugin instanceof EventInterface) {
      $weight = 1;
      $type = 'event';
      $form = $plugin->buildConfigurationForm([], $form_state);
      $values['tokens'] = $plugin->getTokens();
    }
    elseif ($plugin instanceof ConditionInterface) {
      $weight = 2;
      $type = 'condition';
      $form = $plugin->buildConfigurationForm([], $form_state);
    }
    elseif ($plugin instanceof ActionInterface) {
      $weight = 3;
      $type = 'action';
      $form = $this->actionServices->getConfigurationForm($plugin, $form_state);
      if ($form === NULL) {
        return NULL;
      }
    }
    else {
      $weight = 4;
      $type = 'error';
      $form = [];
    }
    $values['path'] = sprintf('plugins/%s/%ss',
      $basePath,
      $type
    );
    $values['provider_path'] = sprintf('plugins/%s',
      $basePath,
    );
    $fields = [];
    $extraDescriptions = [];
    foreach ($form as $key => $def) {
      if (empty($def)) {
        continue;
      }
      switch ($def['#type'] ?? 'markup') {
        case 'hidden':
        case 'actions':
          continue 2;

        case 'item':
        case 'markup':
        case 'container':
          if (isset($def['#markup']) && !str_starts_with($key, 'eca_token_')) {
            $extraDescriptions[] = (string) $def['#markup'];
          }
          continue 2;

        default:
          $fields[] = [
            'name' => $key,
            'label' => $def['#title'] ?? $key,
            'description' => $def['#description'] ?? '',
          ];
      }
    }
    $values['weight'] = $weight;
    $values['type'] = $type;
    $values['fields'] = $fields;
    $values['extraDescriptions'] = $extraDescriptions;
    return $values;
  }

  /**
   * Creates documentation for the given ECA model.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity for which documentation should be created.
   */
  private function modelDoc(Eca $eca): void {
    $model = $eca->getModel();
    $modeller = $eca->getModeller();
    if ($modeller === NULL) {
      return;
    }
    $tags = $model->getTags();
    if (empty($tags) || (count($tags) === 1 && ($tags[0] === 'untagged' || $tags[0] === ''))) {
      // Do not export models without at least one tag.
      return;
    }

    $values = [
      'rawid' => $eca->id(),
      'id' => str_replace([':', ' '], '_', mb_strtolower($eca->label())),
      'label' => $eca->label(),
      'version' => $eca->get('version'),
      'changelog' => $modeller->getChangelog(),
      'main_tag' => $tags[0],
      'tags' => $tags,
      'documentation' => $model->getDocumentation(),
      'events' => [],
      'conditions' => [],
      'actions' => [],
      'model_filename' => $modeller->getPluginId() . '-' . $eca->id(),
      'library_path' => 'library/' . $tags[0],
      'namespace' => ExportRecipe::DEFAULT_NAMESPACE,
    ];
    foreach ($eca->getUsedEvents() as $event) {
      $label = $eca->getEventInfo($event);
      $plugin = $event->getPlugin();
      if (!empty($plugin->getPluginDefinition()['nodocs'])) {
        continue;
      }
      $info = $this->getPluginValues($plugin);
      $id = str_replace(':', '_', $plugin->getPluginId());
      $values['events'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
    }
    $values['events'] = array_unique($values['events']);
    foreach ($eca->getConditions() as $condition) {
      if ($plugin = $this->conditionServices->createInstance($condition['plugin'])) {
        if (!empty($plugin->getPluginDefinition()['nodocs'])) {
          continue;
        }
        $label = $condition['label'] ?? $plugin->getPluginDefinition()['label'];
        $info = $this->getPluginValues($plugin);
        $id = str_replace(':', '_', $plugin->getPluginId());
        $values['conditions'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
      }
    }
    $values['conditions'] = array_unique($values['conditions']);
    foreach ($eca->getActions() as $action) {
      if ($plugin = $this->actionServices->createInstance($action['plugin'])) {
        if (!empty($plugin->getPluginDefinition()['nodocs'])) {
          continue;
        }
        $label = $action['label'] ?? $plugin->getPluginDefinition()['label'];
        $info = $this->getPluginValues($plugin);
        $id = str_replace(':', '_', $plugin->getPluginId());
        $values['actions'][] = '[' . $label . '](/' . $info['path'] . '/' . $id . '.md)';
      }
    }
    $values['actions'] = array_unique($values['actions']);

    @$this->fileSystem->mkdir('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'], NULL, TRUE);

    $archiveFileName = '../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '/' . $values['model_filename'] . '.tar.gz';
    $values['dependencies'] = $this->modellerServices->exportArchive($eca, $archiveFileName);

    file_put_contents('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '.md', $this->render(__DIR__ . '/../../../templates/docs/library.md.twig', $values));
    file_put_contents('../mkdocs/docs/' . $values['library_path'] . '/' . $values['id'] . '/' . $values['model_filename'] . '.xml', $model->getModeldata());

    $this->toc[$values['main_tag']][$values['label']] = $values['library_path'] . '/' . $values['id'] . '.md';
  }

  /**
   * Renders a twig template in filename with given values.
   *
   * @param string $filename
   *   The filename of a twig template.
   * @param array $values
   *   The values for rendering.
   *
   * @return string
   *   The rendered result of the twig template.
   */
  private function render(string $filename, array $values): string {
    $this->twigLoader->setTemplate($filename, file_get_contents($filename));
    try {
      return $this->twigEnvironment->render($filename, $values);
    }
    catch (LoaderError | RuntimeError | SyntaxError) {
      // @todo Log these exceptions.
    }
    return '';
  }

}
