<?php

namespace Drupal\ai_agents_extra\Plugin\AiAgent;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\PluginBase\AiAgentBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\ViewsPluginManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The views agent.
 */
#[AiAgent(
  id: 'views_agent',
  label: new TranslatableMarkup('Views Agent'),
)]
class ViewsAgent extends AiAgentBase {

  /**
   * Questions to ask.
   *
   * @var array
   */
  protected $questions;

  /**
   * The full data of the initial task.
   *
   * @var array
   */
  protected $data;

  /**
   * Task type.
   *
   * @var string
   */
  protected $taskType;

  /**
   * The views executable.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $viewExec;

  /**
   * Garbage collector.
   *
   * @var array
   */
  protected $garbage = [];

  /**
   * The config typed.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $configTyped;

  /**
   * The views handler manager.
   *
   * @var \Drupal\views\ViewsHandlerManager
   */
  protected ViewsHandlerManager $viewsHandlerManager;

  /**
   * The Views plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager
   */
  protected ViewsPluginManager $viewsPluginManager;

  /**
   * The Views style manager.
   *
   * @var \Drupal\views\Plugin\ViewsStyleManager
   */
  protected ViewsPluginManager $viewsStyleManager;

  /**
   * The Views filter manager.
   *
   * @var \Drupal\views\ViewsHandlerManager
   */
  protected ViewsHandlerManager $viewsFilterManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->configTyped = $container->get('config.typed');
    $parent_instance->viewsHandlerManager = $container->get('plugin.manager.views.field');
    $parent_instance->viewsPluginManager = $container->get('plugin.manager.views.display');
    $parent_instance->viewsStyleManager = $container->get('plugin.manager.views.style');
    $parent_instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $parent_instance->fieldManager = $container->get('entity_field.manager');
    $parent_instance->viewsFilterManager = $container->get('plugin.manager.views.filter');
    return $parent_instance;
  }

  /**
   * Collect garbage on failures.
   */
  public function garbageCollect() {
    foreach ($this->garbage as $item) {
      $item->delete();
    }
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      'Views Agent',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [
      'views_agent' => [
        'name' => 'Views Agent',
        'description' => "This is an agent that can create Drupal Views. This means that they can be used to setup a web view around an entity type, including pages, blocks and if plugins exists maps, data exports like csv, json etc. This agent only needs a valid entity type to start.",
        'usage_instructions' => "If they ask you to create a report, page or list, assume in Drupal they mean Views.",
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'Any information needed to generate, edit, delete or information about Drupal Views.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about the Views or the action that was taken.',
            'type' => 'string',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isAvailable() {
    // Check if node module is installed.
    return $this->agentHelper->isModuleEnabled('views');
  }

  /**
   * {@inheritDoc}
   */
  public function isNotAvailableMessage() {
    return $this->t('You need to enable the Views module to do this.');
  }

  /**
   * {@inheritDoc}
   */
  public function getRetries() {
    return 2;
  }

  /**
   * {@inheritDoc}
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritDoc}
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
    $this->data[0]['action'] = 'create';
  }

  /**
   * {@inheritDoc}
   */
  public function answerQuestion() {
    $data = $this->agentHelper->runSubAgent('answerQuestion', [
      'Summary' => $this->getDescription(),
      'Help Text' => $this->getHelp(),
      'Inputs' => $this->getInputsAsString(),
      'Outputs' => $this->getOutputsAsString(),
    ]);

    $answer = "";
    if (isset($data[0]['answer'])) {
      foreach ($data as $dataPoint) {
        $answer .= $dataPoint['answer'] . "\n";
      }
      return $answer;
    }

    return $this->t("Sorry, I got no answers for you.");
  }

  /**
   * {@inheritDoc}
   */
  public function getHelp() {
    $help = $this->t("This agent can figure out content types of a file. Just upload and ask.");
    return $help;
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess() {
    // Check for permissions.
    if (!$this->currentUser->hasPermission('administer views')) {
      return AccessResult::forbidden();
    }
    return parent::hasAccess();
  }

  /**
   * {@inheritDoc}
   */
  public function determineSolvability() {
    parent::determineSolvability();
    $this->data = $this->determineTypeOfTask();

    switch ($this->data[0]['action']) {
      case 'create':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'edit':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'delete':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'information':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'fail':
        return AiAgentInterface::JOB_NOT_SOLVABLE;
    }

    return AiAgentInterface::JOB_NOT_SOLVABLE;
  }

  /**
   * {@inheritDoc}
   */
  public function askQuestion() {
    return $this->questions;
  }

  /**
   * {@inheritDoc}
   */
  public function solve() {
    // Get the view executable.
    $this->getViewExec();
    $message = '';
    switch ($this->data[0]['action']) {
      case 'create':
        try {
          // We need to build this in steps.
          if (empty($this->data[0]['show_mode']) || $this->data[0]['show_mode'] == 'content') {
            $this->determineViewType();
          }
          else {
            $this->determineFieldTypes();
          }
          $this->determineFilterTypes();
          $message = "Your view is here: " . Url::fromRoute('entity.view.edit_form', [
            'view' => $this->data[0]['data_name'],
          ])->toString();
        }
        catch (\Exception $e) {
          $message = 'There was an error creating the node type: ' . $e->getMessage();
        }
        break;

      case 'edit':
        // @todo Implement edit.
        break;

      case 'delete':
        $message = 'This agent does not support deleting views.';
        break;

      case 'fail':
        return $this->data[0]['extra_info'];

      case 'information':
        return $this->answerQuestion();
    }
    return $message;
  }

  /**
   * Check so all requirements are there.
   *
   * @return bool
   *   If all requirements are there.
   */
  public function checkRequirements() {
    return TRUE;
  }

  /**
   * Determine if the context is asking a question or wants a audit done.
   *
   * @return string
   *   The context.
   */
  public function determineTypeOfTask() {
    $data = $this->agentHelper->runSubAgent('determineViewsTask', [
      'Possible Views Types' => $this->getViewsTypes(),
      'Possible Views Styles' => $this->getViewsStyles(),
      'Entity Types and Bundles' => $this->getEntityTypesAndBundles(),
    ]);

    if (isset($data[0]['action']) && in_array($data[0]['action'], [
      'create',
      'edit',
      'delete',
      'information',
      'fail',
    ])) {
      return $data;
    }
    throw new \Exception('Invalid action in determining the task.');
  }

  /**
   * Set the view type.
   */
  public function determineViewType() {
    $display = $this->viewExec->getDisplay('default');
    $rowOptions = $display->getOption('row');
    $rowOptions['type'] = 'entity:' . $this->data[0]['entity_type'];
    $rowOptions['options']['relationship'] = 'none';
    $rowOptions['options']['view_mode'] = $this->data[0]['content_mode'];
    $display->setOption('row', $rowOptions);
    $this->viewExec->setDisplay('default', $display);
    $this->viewExec->save();
  }

  /**
   * Determine the field types.
   */
  public function determineFieldTypes() {
    $data = $this->agentHelper->runSubAgent('determineFieldsTask', [
      'Requested Field Types' => !empty($this->data[0]['field_types']) ? implode(', ', $this->data[0]['field_types']) : 'No field types specified.',
      'Available Field Types' => $this->getFieldTypes(),
      'Available Plugin Types' => $this->getFieldPluginOptions(),
    ]);
    if (empty($data[0]['action'])) {
      throw new \Exception('We could not determine which field types was needed for the view.');
    }
    // Load the display, to add fields.
    $display = $this->viewExec->getDisplay('default');
    $fieldsDisplay = $display->getOption('fields');

    // Then try to generate settings per field.
    foreach ($data as $field) {
      // Check where this field is stored.
      $table = $this->getStorageTable($field['field'], $this->data[0]['entity_type']);
      if ($table || $field['plugin_id'] == 'entity_operations') {
        // Get the config for the field type.
        $options = $this->getFieldConfigs($field['plugin_id']);
        // Set some values directly.
        $setOptions = [
          'id' => $field['id'],
          'table' => $table,
          'field' => $field['field'],
          'label' => $field['label'],
          'admin_label' => $field['label'],
          'plugin_id' => $field['plugin_id'],
        ];
        $fieldData = $this->agentHelper->runSubAgent('determineFieldConfig', [
          'Field Type' => $field['type'] ?? 'No type specified.',
          'View Style' => $this->data[0]['view_style'],
          'Field Configuration' => Yaml::dump($options['mapping'], 12, 4),
          'Current Options' => Yaml::dump($setOptions, 12, 4),
        ]);

        if ($fieldData[0]['action'] == 'change') {
          $setOptions = array_merge_recursive($setOptions, $fieldData[0]['new_config']);
        }
        // Some special cases.
        if (!empty($field['type'])) {
          switch ($field['type']) {
            case 'image':
              if ($this->data[0]['view_style'] == 'table') {
                $setOptions['settings'] = [
                  'image_style' => 'thumbnail',
                  'image_link' => 'content',
                ];
              }
              break;
          }
        }
        if ($field['plugin_id'] == 'entity_operations') {
          $setOptions['table'] = 'node';
        }
        // Generate a views field.
        $fieldsDisplay[$setOptions['id']] = $setOptions;
      }
    }
    $display->setOption('fields', $fieldsDisplay);
    $this->viewExec->setDisplay('default', $display);
    $this->viewExec->save();
  }

  /**
   * Determine the filter types.
   */
  public function determineFilterTypes() {
    $this->viewExec = Views::getView($this->data[0]['data_name']);
    $data = $this->agentHelper->runSubAgent('determineFilterTypes', [
      'Bundle' => $this->data[0]['bundle'] ?? 'No bundle specified.',
      'Administrative View' => $this->data[0]['administration_theme'] ? 'Yes' : 'No',
      'Available Field Types' => $this->getFieldTypes(),
      'Available Filter Plugin Types' => $this->getFilterPluginOptions(),
    ]);

    if (empty($data[0]['action'])) {
      throw new \Exception('We could not determine which filter types was needed for the view.');
    }
    // Load the display, to add fields.
    $display = $this->viewExec->getDisplay('default');
    $filtersDisplay = $display->getOption('filters');

    // Then try to generate settings per field.
    foreach ($data as $field) {
      // Check where this field is stored.
      $table = $this->getStorageTable($field['field'], $this->data[0]['entity_type']);

      if ($table) {
        if ($field['id'] == 'type') {
          // Don't do this.
          continue;
        }
        // If its admin table and status.
        if ($field['id'] == 'status' && $this->data[0]['administration_theme']) {
          $field['value'] = 'All';
        }
        // Should have suffix.
        // @todo Better way to determine this.
        $suffix = (str_contains($field['field'], 'field_') && !str_contains($field['field'], '_value')) || $field['field'] == 'body' ? '_value' : '';
        // Special for taxonomy.
        if ($field['plugin_id'] == 'taxonomy_index_tid') {
          $suffix = '_target_id';
        }
        // Set some values directly.
        $setOptions = [
          'id' => $field['id'] . $suffix,
          'table' => $table,
          'field' => $field['field'] . $suffix,
          'admin_label' => $field['label'],
          'exposed' => $field['exposed'],
          'plugin_id' => $field['plugin_id'],
          'relationship' => 'none',
          'group' => 1,
          'group_type' => 'group',
          'value' => $field['value'],
          'entity_type' => $this->data[0]['entity_type'],
          'entity_field' => $field['field'],
          'operator' => $field['operator'],
          'expose' => [
            'identifier' => $field['id'] . '_op',
            'label' => $field['label'],
            'use_operator' => FALSE,
            'description' => '',
          ],
          'is_grouped' => FALSE,
          'group_info' => [
            'label' => '',
            'description' => '',
            'identifier' => '',
            'optional' => TRUE,
            'widget' => 'select',
            'multiple' => FALSE,
            'remember' => FALSE,
            'default_group' => 'All',
            'default_group_multiple' => [],
            'group_items' => [],
          ],
        ];
        if ($field['plugin_id'] == 'taxonomy_index_tid') {
          $setOptions['expose']['identifier'] = $field['id'] . '_target_id';
          $setOptions['expose']['operator'] = $field['id'] . '_target_id_op';
          $setOptions['expose']['operator_id'] = $field['id'] . '_target_id_op';
          $setOptions['expose']['operator_limit_selection'] = FALSE;
          unset($setOptions['entity_field']);
          unset($setOptions['entity_type']);
          $setOptions['vid'] = $field['vid'];
          $setOptions['type'] = 'select';
          $setOptions['value'] = [];
          $setOptions['hierarchy'] = TRUE;
          $setOptions['limit'] = TRUE;

        }
        $filtersDisplay[$setOptions['id']] = $setOptions;
      }
    }
    // If a bundle is set, we set the filter for that.
    if (!empty($this->data[0]['bundle'])) {
      $setOptions = [
        'id' => 'type',
        'table' => 'node_field_data',
        'field' => 'type',
        'admin_label' => 'Content type',
        'plugin_id' => 'bundle',
        'value' => [
          $this->data[0]['bundle'] => $this->data[0]['bundle'],
        ],
      ];
      $filtersDisplay[$setOptions['id']] = $setOptions;
    }
    $display->setOption('filters', $filtersDisplay);
    $this->viewExec->setDisplay('default', $display);
    $this->viewExec->save();
  }

  /**
   * Returns all the configs for a field type.
   *
   * @param string $fieldType
   *   The field type.
   *
   * @return array
   *   The field type configs.
   */
  public function getFieldConfigs($fieldType) {
    $typedViews = $this->configTyped;
    $typed = $typedViews->getDefinition('views.field.' . $fieldType);
    // If not available, load default.
    if (empty($typed)) {
      $typed = $typedViews->getDefinition('views.field.default');
    }
    return $typed;
  }

  /**
   * Get a list of all field types for this entity type.
   *
   * @return string
   *   The list of field types.
   */
  public function getFieldTypes() {
    $fields = $this->fieldManager->getFieldDefinitions($this->data[0]['entity_type'], $this->data[0]['bundle']);
    $list = [];
    foreach ($fields as $field) {
      $list[] = $field->getLabel() . ' (data_name: ' . $field->getName() . ', field_type: ' . $field->getType() . ') ' . $field->getDescription();
    }
    return implode("\n", $list);
  }

  /**
   * Get filter plugin options as string.
   *
   * @return string
   *   The list of filter plugin options.
   */
  public function getFilterPluginOptions() {
    $filters = $this->viewsFilterManager->getDefinitions();
    $list = [];
    foreach ($filters as $filter) {
      $list[] = $filter['id'];
    }
    return implode("\n", $list);
  }

  /**
   * Get field plugin options as string.
   *
   * @return string
   *   The list of field plugin options.
   */
  public function getFieldPluginOptions() {
    $fields = $this->viewsHandlerManager->getDefinitions();
    $list = [];
    foreach ($fields as $field) {
      $typed = $this->configTyped->getDefinition('views.field.' . $field['id']);
      $list[] = $typed['label'] . ' (' . $field['id'] . ')';
    }
    return implode("\n", $list);
  }

  /**
   * Get a list of all Views types.
   *
   * @return string
   *   The list of Views types.
   */
  public function getViewsTypes() {
    $formats = $this->viewsPluginManager->getDefinitions();
    $list = [];
    foreach ($formats as $plugin_definition) {
      $list[] = $plugin_definition['id'];
    }
    return implode(', ', $list);
  }

  /**
   * Get a list of all Views formats, like tables, lists, etc.
   *
   * @return string
   *   The list of Views formats.
   */
  public function getViewsStyles() {
    $formats = $this->viewsStyleManager->getDefinitions();
    $list = [];
    foreach ($formats as $plugin_definition) {
      $list[] = $plugin_definition['id'];
    }
    return implode(', ', $list);
  }

  /**
   * Get a list of all content entity types and bundles.
   *
   * @return string
   *   The list of content entity types and bundles.
   */
  public function getEntityTypesAndBundles() {
    $nodeTypes = $this->entityTypeBundleInfo->getAllBundleInfo();
    $list = "";
    foreach ($nodeTypes as $entityType => $bundles) {
      foreach ($bundles as $bundle => $bundleData) {
        $list .= $bundleData['label'] . ' (entity_type: ' . $entityType . ', bundle: ' . $bundle . ")\n";
      }
    }
    return $list;
  }

  /**
   * Get table of storage in Drupal.
   */
  public function getStorageTable($fieldName, $entityType, $revision = FALSE) {
    $type = $this->entityTypeManager->getDefinition($entityType);
    // Only list content entity types using SQL storage.
    if ($type instanceof ContentEntityTypeInterface && in_array(SqlEntityStorageInterface::class, class_implements($type->getStorageClass()))) {
      $storage = $this->entityTypeManager->getStorage($type->id());

      foreach ($this->fieldManager->getFieldStorageDefinitions($type->id()) as $field) {
        if ($fieldName != $field->getName()) {
          continue;
        }
        $keys = $storage->getTableMapping()->getAllFieldTableNames($field->getName());
        if ($revision && isset($keys[1])) {
          return $keys[1];
        }
        elseif (!$revision && isset($keys[0])) {
          return $keys[0];
        }
      }
    }
    return FALSE;
  }

  /**
   * Gets the views executable.
   */
  protected function getViewExec() {
    try {
      $this->viewExec = Views::getView($this->data[0]['data_name']);
      if (!empty($this->viewExec)) {
        return;
      }
    }
    catch (\Exception $e) {
      // Do nothing.
    }

    // If its create, we create a new view.
    if ($this->data[0]['action'] === 'create') {
      $display = [
        'id' => 'default',
        'display_title' => 'default',
        'enabled' => TRUE,
        'display_options' => [
          'title' => $this->data[0]['title'],
          'use_pager' => $this->data[0]['pager'] == 'none' ? FALSE : TRUE,
          'style' => [
            'type' => $this->data[0]['view_style'],
          ],
          'pager' => [
            'type' => $this->data[0]['pager'],
            'options' => [
              'items_per_page' => $this->data[0]['amount_per_page'],
            ],
          ],
          'path' => trim($this->data[0]['path'], '/'),
        ],
        'display_plugin' => 'default',
        'position' => 0,
        'cache_metadata' => [
          'tags' => [],
        ],
      ];
      if (isset($this->data[0]['permission'])) {
        $display['display_options']['access']['type'] = 'perm';
        $display['display_options']['access']['options']['perm'] = $this->data[0]['permission'];
      }
      // Create another display as needed.
      $type = $this->data[0]['view_type'];
      $visibleDisplay = [
        'id' => $type . '_1',
        'display_title' => $this->data[0]['title'],
        'display_plugin' => $type,
        'enabled' => TRUE,
        'position' => 1,
        'display_options' => [
          'path' => trim($this->data[0]['path'], '/'),
          'display_extenders' => [],
        ],
        'cache_metadata' => [
          'tags' => [],
          'max-age' => 0,
        ],
      ];
      $view = View::create([
        'id' => $this->data[0]['data_name'],
        'label' => $this->data[0]['title'],
        'module' => 'views',
        'description' => $this->data[0]['description'],
        'tag' => 'default',
        // @todo load from config.
        'base_table' => $this->data[0]['entity_type'] . '_field_data',
        'display' => [
          'default' => $display,
          $type . '_1' => $visibleDisplay,
        ],
      ]);

      try {
        $view->save();
      }
      catch (\Exception $e) {
        throw new \Exception('Could not save the view: ' . $e->getMessage());
      }
      $this->garbage[] = $view;
      $this->viewExec = Views::getView($this->data[0]['data_name']);
    }
    else {
      $this->viewExec = Views::getView($this->data[0]['data_name']);
    }
    if (empty($this->viewExec)) {
      throw new \Exception('The view does not exist.');
    }
  }

}
