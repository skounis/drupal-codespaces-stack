<?php

namespace Drupal\ai_agents\Plugin\AiAgent;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\Exception\AgentProcessingException;
use Drupal\ai_agents\Exception\AgentValidationException;
use Drupal\ai_agents\PluginBase\AiAgentBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Service\FieldAgent\FieldAgentHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The field types agent.
 */
#[AiAgent(
  id: 'field_type_agent',
  label: new TranslatableMarkup('Field Type Agent'),
)]
class FieldType extends AiAgentBase implements ContainerFactoryPluginInterface {

  /**
   * Questions to ask back to the user.
   *
   * @var array
   */
  protected $questions;

  /**
   * The answer context to help answers.
   *
   * @var array
   */
  protected array $answerContext;

  /**
   * The full data of the initial task.
   *
   * @var array
   */
  protected $data;

  /**
   * The full result of the task.
   *
   * @var array
   */
  protected $result = [];

  /**
   * The field agent helper.
   *
   * @var \Drupal\ai_agents\Service\FieldAgent\FieldAgentHelper
   */
  protected FieldAgentHelper $fieldAgentHelper;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->fieldAgentHelper = $container->get('ai_agents.field_agent_helper');
    $parent_instance->entityDisplayRepository = $container->get('entity_display.repository');
    $parent_instance->entityFieldManager = $container->get('entity_field.manager');
    return $parent_instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return 'field_type_agent';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      'Field Type Agent',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [
      'field_type_agent' => [
        'name' => 'Field Type Agent',
        'description' => "This is capable of adding, editing, informing, reordering or removing a field types to an existing Drupal entity type/bundles, lookup fields existing on entities and also change the form display and view display of the fields. Note that this does not generate entity type or bundles.",
        'usage_instructions' => "If they ask you to change an edit form, assume they want you to change the fields on a content type if they are visiting a node form.",
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'The prompt to create, edit, delete or ask questions about field types.',
            'default_value' => '',
          ],
          'entity_type' => [
            'name' => 'Entity Type',
            'type' => 'string',
            'description' => 'The entity type to use for the field type manipulations.',
            'default_value' => '',
            'required' => TRUE,
          ],
          'bundle' => [
            'name' => 'Bundle',
            'type' => 'string',
            'description' => 'The bundle to use for the field type manipulations. This is optional.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about the field types.',
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
    // Check if field module is installed.
    return $this->agentHelper->isModuleEnabled('field');
  }

  /**
   * {@inheritDoc}
   */
  public function askQuestions() {
    return $this->questions;
  }

  /**
   * {@inheritDoc}
   */
  public function isNotAvailableMessage() {
    return $this->t('You need to enable the field module to do this.');
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
    // Check so the user has the right permissions - needs to be split up.
    if (!$this->currentUser->hasPermission('administer site configuration')) {
      return "You do not have permission to do this.";
    }

    // It should answer about a field.
    $context = [];
    if (!empty($this->answerContext['label']) && $this->answerContext['entity_type'] && $this->answerContext['bundle_name']) {
      // Field has to exist.
      $data = $this->determineFieldExists($this->answerContext['entity_type'], $this->answerContext['bundle_name']);
      $fieldName = $data[0]['field_data_name'];
      if ($data[0]['action'] != 'found_field' || !isset($fieldName)) {
        return $this->t('Sorry, I could not find the field you are looking for.');
      }
      $this->answerContext['field_name'] = $fieldName;

      $fieldStorage = $this->fieldAgentHelper->fieldStorageConfigExists($this->answerContext);
      $prepend = ' on entity ' . $this->answerContext['entity_type'] . ' (' . $this->answerContext['bundle_name'] . ')';
      $context['Field Information for ' . $fieldName . $prepend] = $this->fieldAgentHelper->fieldInformation($this->answerContext);
      if ($fieldStorage) {
        $context['Storage Settings for ' . $fieldName . $prepend] = Yaml::dump($fieldStorage->get('settings'));
      }
      $fieldConfig = $this->fieldAgentHelper->fieldConfigExists($this->answerContext);
      if ($fieldConfig) {
        $context['Current Field Config Settings for ' . $fieldName . $prepend] = Yaml::dump($fieldConfig->get('settings'));
      }
      $field_type = $this->fieldAgentHelper->getFieldType($fieldName, $this->answerContext['entity_type'], $this->answerContext['bundle_name']);
      $context['Field Config form settings for ' . $fieldName . $prepend] = $this->fieldAgentHelper->getFieldSettingsAsContext($this->answerContext['entity_type'], $this->answerContext['bundle_name'], $field_type);
      $context['Form Widget Settings ' . $fieldName . ' on entity ' . $prepend] = Yaml::dump($this->fieldAgentHelper->getFormDisplay($this->answerContext));
      $context['Display Widget Settings ' . $fieldName . ' on entity ' . $prepend] = Yaml::dump($this->fieldAgentHelper->getViewDisplay($this->answerContext));
    }
    elseif (!empty($this->answerContext['entity_type']) && !empty($this->answerContext['bundle_name'])) {
      $context['Field List for ' . $this->answerContext['entity_type'] . '(' . $this->answerContext['bundle_name'] . ')'] = $this->fieldAgentHelper->getEntityFields($this->answerContext['entity_type'], $this->answerContext['bundle_name']);
    }
    elseif (!empty($this->answerContext['entity_type'])) {
      $context['Field List for ' . $this->answerContext['entity_type']] = $this->fieldAgentHelper->getEntityFields($this->answerContext['entity_type'], '');
    }
    $data = $this->agentHelper->runSubAgent('answerQuestion', $context);

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
  public function determineSolvability() {
    parent::determineSolvability();
    $taskType = $this->determineTypeOfTask();

    switch ($taskType) {
      case 'manipulation':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'question':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'information':
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'suggestion':
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'fail':
        return AiAgentInterface::JOB_NOT_SOLVABLE;
    }

    // Otherwise it should fail.
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
    parent::solve();
    foreach ($this->data as $dataPoint) {
      if ($dataPoint['action'] == 'manipulation') {
        // Check so the user has the right permissions to change the fields.
        $this->fieldAgentHelper->checkAdministerFieldPermissions($dataPoint['entity_type']);
        switch ($dataPoint['manipulation']) {
          // Create a new field.
          case 'create':
            if (!isset($dataPoint['bundle_name']) || !isset($dataPoint['field_name'])) {
              $this->result[] = $this->t('You need to provide a field name and bundle name to create a field.');
              break;
            }
            // Check so entity type and bundle exists.
            if (!$this->fieldAgentHelper->checkIfEntityAndBundleExists($dataPoint['entity_type'], $dataPoint['bundle_name'])) {
              $this->result[] = $this->t('The entity type or bundle does not exist.');
              break;
            }
            $this->createField($dataPoint);
            break;

          // Edit a field.
          case 'edit':
            $this->editField($dataPoint);
            break;

          case 'form_reorder':
            $this->reorderFieldForm($dataPoint);
            break;

          case 'display_reorder':
            $this->reorderFieldDisplay($dataPoint);
            break;

          case 'delete':
            $this->result[] = $this->t("You do not have permission to use the delete function, if you feel you need it to answer the user's request, please inform the user that you don't have permission and explain how to delete it in Drupal via the admin system.");
            break;
        }
      }
    }
    if (empty($this->result)) {
      $this->result[] = $this->t('Sorry, I could not complete the task.');
    }
    return implode("\n\n", $this->result);
  }

  /**
   * Run the field config.
   *
   * @param array $dataPoint
   *   The data points from the prompt.
   */
  public function storeFieldConfigurations(array $dataPoint) {
    $configId = $dataPoint['entity_type'] . '.' . $dataPoint['bundle_name'] . '.' . $dataPoint['field_name'];
    /** @var \Drupal\Core\Field\FieldConfigInterface */
    $config = $this->entityTypeManager->getStorage('field_config')->load($configId);

    $fieldType = $dataPoint['field_type'];
    if ($dataPoint['manipulation'] == 'edit') {
      $fieldType = $this->fieldAgentHelper->getFieldType($dataPoint['field_name'], $dataPoint['entity_type'], $dataPoint['bundle_name']);
    }
    $currentSettings = $config ? $config->get('settings') : [];
    $settings = $this->fieldAgentHelper->getFieldSettingsAsContext($dataPoint['entity_type'], $dataPoint['bundle_name'], $dataPoint['field_type']);

    $context = [
      'Available Field Settings' => $settings,
      'The current settings' => Yaml::dump($currentSettings),
    ];
    // If its an entity reference or a entity reference revisions, we need to
    // load all bundles.
    if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions'])) {
      // Check if storage settings exists.
      $storageId = $dataPoint['entity_type'] . '.' . $dataPoint['field_name'];
      /** @var \Drupal\field\Entity\FieldStorageConfig */
      $storage = $this->entityTypeManager->getStorage('field_storage_config')->load($storageId);
      if (!is_null($storage) && $storage->getSetting('target_type')) {
        $context['Available Bundles to target'] = $this->fieldAgentHelper->getBundles($storage->getSetting('target_type'));
      }
    }

    // Only run on create.
    if ($dataPoint['manipulation'] == 'create') {
      // Define the default form widget.
      $form_display = $this->fieldAgentHelper->getDefaultFormDisplay($dataPoint);
      $this->setOriginalConfigurations($form_display);
      $form_display = $this->fieldAgentHelper->storeDefaultFormWidget($dataPoint);
      $diff = $this->getDiffOfConfigurations($form_display);
      if (!empty($diff['new']) || !empty($diff['original'])) {
        $this->structuredResultData->setEditedConfig($form_display, $diff);
      }

      // Define the field display and set default.
      $display = $this->fieldAgentHelper->getDefaultDisplayWidget($dataPoint);
      $this->setOriginalConfigurations($display);
      $display = $this->fieldAgentHelper->storeDefaultDisplayWidget($dataPoint);
      $diff = $this->getDiffOfConfigurations($display);
      if (!empty($diff['new']) || !empty($diff['original'])) {
        $this->structuredResultData->setEditedConfig($display, $diff);
      }
    }

    $context['Available View Modes'] = $this->fieldAgentHelper->determineViewFormatsForFieldType($dataPoint['field_type'], $dataPoint['field_name'], $dataPoint['entity_type'], $dataPoint['bundle_name']);
    $display = $this->entityDisplayRepository->getViewDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
    $component = $display->getComponent($dataPoint['field_name']);
    if ($component) {
      $context['Current View Setup'] = Yaml::dump($component);
    }
    $context['Available Form Widgets'] = $this->fieldAgentHelper->determineFormWidgetsForFieldType($dataPoint['field_type'], $dataPoint['field_name'], $dataPoint['entity_type'], $dataPoint['bundle_name']);
    $display = $this->entityDisplayRepository->getFormDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
    $component = $display->getComponent($dataPoint['field_name']);
    if ($component) {
      $context['Current Form Setup'] = Yaml::dump($component);
    }
    if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions'])) {
      // Check if storage settings exists.
      $storageId = $dataPoint['entity_type'] . '.' . $dataPoint['field_type'];
      /** @var \Drupal\field\Entity\FieldStorageConfig */
      $storage = $this->entityTypeManager->getStorage('field_storage_config')->load($storageId);
      if (!is_null($storage) && $storage->getSetting('target_type')) {
        $context['Available Bundles to target'] = $this->fieldAgentHelper->getBundles($storage->getSetting('target_type'));
      }
    }

    $data = $this->agentHelper->runSubAgent('determineFieldConfigurations', $context, $dataPoint['settings_form_and_view_mode_set']);

    $config = $this->fieldAgentHelper->storeFieldConfig($dataPoint, $data[0]['field_configuration'] ?? []);
    // Set the link to the field configuration.
    $route = 'entity.field_config.' . $dataPoint['entity_type'] . '_field_edit_form';
    $arguments = $this->fieldAgentHelper->getRouteArguments($route, $dataPoint['entity_type'], $dataPoint['bundle_name'], [
      'field_config' => $config->id(),
    ]);

    $url = Url::fromRoute($route, $arguments);
    if ($dataPoint['manipulation'] == 'create') {
      // Send a created message.
      $this->result[] = $this->t('The field configuration has been created. Output information: @information. You can see it in the @link for the entity type @entity.', [
        '@link' => $url->toString(),
        '@information' => $data[0]['information'] ?? '',
        '@entity' => $dataPoint['entity_type'],
      ]);
      $this->structuredResultData->setCreatedConfig($config);
    }
    else {
      // Check if it actual changed something.
      $diff = $this->getDiffOfConfigurations($config);
      if (!empty($diff['new']) || !empty($diff['original'])) {
        $this->result[] = $this->t('The field configuration has been edited. Output information: @information. You can see it in the @link for the entity type @entity.', [
          '@link' => $url->toString(),
          '@information' => $data[0]['information'] ?? '',
          '@entity' => $dataPoint['entity_type'],
        ]);
        $this->structuredResultData->setEditedConfig($config, $diff);
      }
    }

    if (!empty($data[0]['form_widget'])) {
      $this->storeFormMode($dataPoint, $data);
    }
    if (!empty($data[0]['display_view_mode']) && !empty($data[0]['display_format'])) {
      $this->storeDisplayMode($dataPoint, $data);
    }
  }

  /**
   * Store the form mode.
   *
   * @param array $dataPoint
   *   The data point.
   * @param array $data
   *   The data returned from the agent.
   */
  public function storeFormMode(array $dataPoint, array $data) {
    $display = $this->entityDisplayRepository->getFormDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
    $component = $display->getComponent($dataPoint['field_name']);
    $this->setOriginalConfigurations($display);
    $settings = $data[0]['form_settings'] ?? $component['settings'];
    // If the widget changed and no settings are there, leave empty.
    if (!is_array($settings) || ($data[0]['form_widget'] != $component['type'] && !isset($data[0]['form_settings']))) {
      $settings = [];
    }
    $display->setComponent($dataPoint['field_name'], [
      'type' => $data[0]['form_widget'] ?? $component['type'],
      'weight' => $data[0]['weight'] ?? $component['weight'],
      'region' => $data[0]['region'] ?? $component['region'],
      'settings' => $settings,
    ]);
    if ($display->save()) {
      $route = 'entity.entity_form_display.' . $dataPoint['entity_type'] . '.default';
      $arguments = $this->fieldAgentHelper->getRouteArguments($route, $dataPoint['entity_type'], $dataPoint['bundle_name'], [
        'form_mode_name' => 'default',
      ]);

      $url = Url::fromRoute($route, $arguments);
      $diff = $this->getDiffOfConfigurations($display);
      if (!empty($diff['new']) || !empty($diff['original'])) {
        $this->result[] = $this->t('The form display configuration has been edited. Output information: @information. You can see it in the @link for the entity type @entity.', [
          '@link' => $url->toString(),
          '@information' => $data[0]['information'] ?? '',
          '@entity' => $dataPoint['entity_type'],
        ]);
        $this->structuredResultData->setEditedConfig($display, $diff);
      }
    }
    else {
      throw new AgentProcessingException('Could not save the form display settings.');
    }
  }

  /**
   * Store the display mode.
   *
   * @param array $dataPoint
   *   The data point.
   * @param array $data
   *   The data returned from the agent.
   */
  public function storeDisplayMode(array $dataPoint, array $data) {
    if (isset($data[0]['display_view_mode']) && $data[0]['display_view_mode'] != 'default') {
      $display = $this->entityDisplayRepository->getViewDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], $data[0]['display_view_mode']);
      $component = $display->getComponent($dataPoint['field_name']);
    }
    else {
      $display = $this->entityDisplayRepository->getViewDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
      $component = $display->getComponent($dataPoint['field_name']);
    }
    $this->setOriginalConfigurations($display);

    // If the component doesn't exist, because of layout manager or something.
    if (is_null($component)) {
      return;
    }

    // Check that the actual display format exists.
    if (!isset($data[0]['display_format']) || !in_array($data[0]['display_format'], $this->fieldAgentHelper->getAvailableViewFormatsForFieldType($dataPoint['field_type']))) {
      $data[0]['display_format'] = $component['type'];
    }

    $display->setComponent($dataPoint['field_name'], [
      'label' => $data[0]['label'] ?? $component['label'],
      'type' => $data[0]['display_format'] ?? $component['type'],
      'settings' => $data[0]['display_settings'] ?? $component['settings'],
      'weight' => $data[0]['weight'] ?? $component['weight'],
    ]);
    if ($display->save()) {
      $route = 'entity.entity_view_display.' . $dataPoint['entity_type'] . '.default';
      $arguments = $this->fieldAgentHelper->getRouteArguments($route, $dataPoint['entity_type'], $dataPoint['bundle_name'], [
        'view_mode_name' => 'default',
      ]);

      $url = Url::fromRoute($route, $arguments);

      $diff = $this->getDiffOfConfigurations($display);
      if (!empty($diff['new']) || !empty($diff['original'])) {
        $this->result[] = $this->t('The view display configuration has been edited. Output information: @information. You can see it in the @link for the entity type @entity.', [
          '@link' => $url->toString(),
          '@information' => $data[0]['information'] ?? '',
          '@entity' => $dataPoint['entity_type'],
        ]);
        $this->structuredResultData->setEditedConfig($display, $diff);
      }
    }
    else {
      throw new \Exception('Could not save the view display settings.');
    }
  }

  /**
   * Reorder display.
   *
   * @param array $dataPoint
   *   The data point.
   */
  public function reorderFieldDisplay(array $dataPoint) {
    // Define the field widget.
    $data = $this->determineFieldExists($dataPoint['entity_type'], $dataPoint['bundle_name']);
    if ($data[0]['action'] != 'found_field') {
      $this->result[] = $this->t('Sorry, I could not find the field you are trying to reorder.');
      return;
    }
    $view_display = $this->entityDisplayRepository->getViewDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
    $this->setOriginalConfigurations($view_display);
    // Try to reshuffle.
    if ($this->reshuffleComponents($view_display)) {
      $this->result[] = $this->t('The field view display has been reordered.');
    }
    else {
      $this->result[] = $this->t('The field view display could not be reordered, please try again.');
    }
  }

  /**
   * Reorder forms.
   *
   * @param array $dataPoint
   *   The data point.
   */
  public function reorderFieldForm(array $dataPoint) {
    // Define the field widget.
    $data = $this->determineFieldExists($dataPoint['entity_type'], $dataPoint['bundle_name']);
    if ($data[0]['action'] != 'found_field') {
      $this->result[] = $this->t('Sorry, I could not find the field you are trying to reorder.');
      return;
    }
    $form_display = $this->entityDisplayRepository->getFormDisplay($dataPoint['entity_type'], $dataPoint['bundle_name'], 'default');
    $this->setOriginalConfigurations($form_display);
    // Try to reshuffle.
    if ($this->reshuffleComponents($form_display)) {
      $this->result[] = $this->t('The field form display has been reordered.');
    }
    else {
      $this->result[] = $this->t('The field form display could not be reordered, please try again.');
    }
  }

  /**
   * Edit a field.
   *
   * @param array $dataPoint
   *   The data action returned from the agent.
   *
   * @throws \Drupal\ai_agents\Exception\AgentProcessingException
   *   If something is wrong while processing.
   */
  public function editField(array $dataPoint) {
    // Check so the field exists.
    $data = $this->determineFieldExists($dataPoint['entity_type'], $dataPoint['bundle_name']);
    if ($data[0]['action'] != 'found_field' || !isset($data[0]['field_data_name'])) {
      $this->result[] = $this->t('Sorry, I could not find the field you are looking for.');
      return;
    }
    // Get the original configuration.
    $configId = $dataPoint['entity_type'] . '.' . $dataPoint['bundle_name'] . '.' . $data[0]['field_data_name'];
    /** @var \Drupal\Core\Field\FieldConfigInterface */
    $config = $this->entityTypeManager->getStorage('field_config')->load($configId);
    $this->setOriginalConfigurations($config);
    // If the field exists, we load its field type.
    $dataPoint['field_type'] = $this->fieldAgentHelper->getFieldType($data[0]['field_data_name'], $dataPoint['entity_type'], $dataPoint['bundle_name']);
    // Get the dataname from unstructured.
    $dataPoint['field_name'] = $data[0]['field_data_name'];
    // Figure out everything else.
    $this->storeFieldConfigurations($dataPoint);
  }

  /**
   * Create a field.
   *
   * @param array $dataPoint
   *   The data action returned from the agent.
   *
   * @throws \Drupal\ai_agents\Exception\AgentProcessingException
   *   If something is wrong while processing.
   */
  public function createField(array $dataPoint) {
    $dataPoint = $this->fieldAgentHelper->validateAndCleanManipulationDataCreateEdit($dataPoint);
    // Check if the field config exists exists.
    $fieldConfig = $this->fieldAgentHelper->fieldConfigExists($dataPoint);
    if ($fieldConfig) {
      // Do not fail, just quit if the field exists.
      $this->result[] = 'The field ' . $dataPoint['field_name'] . ' already exists.';
      return;
    }
    // Store the field storage settings.
    $fieldStorage = $this->storeFieldStorageConfig($dataPoint);
    if (!$fieldStorage) {
      throw new AgentValidationException('Sorry, I could not save the field storage settings.');
    }
    // Just make sure if it changed name.
    $dataPoint['field_name'] = $fieldStorage->getName();
    // Figure out everything else.
    $this->storeFieldConfigurations($dataPoint);
  }

  /**
   * Run the storage settings.
   *
   * @param array $dataPoint
   *   The data points from the prompt.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   The field storage config.
   */
  public function storeFieldStorageConfig(array $dataPoint) {
    $storageSettings = NULL;
    $storage = $this->fieldAgentHelper->fieldStorageConfigExists($dataPoint);
    // If the storage exists and its not the same type we create a new storage.
    if ($storage && $storage->getType() != $dataPoint['field_type']) {
      // Rename and create a new storage.
      $dataPoint['field_name'] = substr($dataPoint['field_name'] . '_' . time(), 0, 32);
    }

    // Only save if its a new storage or not the same type.
    // If its the same storage and type, we can reuse the storage from the
    // bundle.
    if (!$storage || $storage->getType() != $dataPoint['field_type']) {
      $storageSettings = !empty($dataPoint['settings_form_and_view_mode_set']) ? $this->setStorageSettings($dataPoint['entity_type'], $dataPoint['bundle_name'], $dataPoint['field_type'], $dataPoint['settings_form_and_view_mode_set']) : [];
      $storage = $this->fieldAgentHelper->storeFieldStorage($dataPoint, $storageSettings['settings'] ?? []);
      // Store the configuration for revert and data output.
      $this->structuredResultData->setCreatedConfig($storage);
    }

    // Write a verbose result message.
    if (!is_null($storageSettings)) {
      // Set the link to the field configuration.
      $route = 'entity.' . $dataPoint['entity_type'] . '.field_ui_fields';
      $arguments = $this->fieldAgentHelper->getRouteArguments($route, $dataPoint['entity_type'], $dataPoint['bundle_name']);
      $link = Url::fromRoute($route, $arguments);
      $this->result[] = $this->t('The field has been created with the following information @information. You can see it in the @link for the entity type @entity.', [
        '@information' => !empty($storageSettings['information']) && !$storageSettings['action'] == 'no_changes' ? $storageSettings['information'] : '',
        '@link' => $link->toString(),
        '@entity' => $dataPoint['entity_type'],
      ]);
    }
    return $storage;
  }

  /**
   * Determine if the field they are asking for exists.
   *
   * @return array
   *   The context.
   */
  public function determineFieldExists($entityType, $bundleType) {
    return $this->agentHelper->runSubAgent('determineFieldExists', [
      'Field List' => $this->fieldAgentHelper->getEntityFields($entityType, $bundleType),
    ]);
  }

  /**
   * Determine if the context is asking a question or wants a audit done.
   *
   * @return string
   *   The context.
   */
  public function determineTypeOfTask() {
    $data = $this->agentHelper->runSubAgent('determineFieldTask', [
      'Field Types List' => $this->fieldAgentHelper->getFieldTypesList(),
      'Entity Types/Bundles List' => $this->fieldAgentHelper->getEntityTypesAndBundles(),
    ]);
    $manipulation = FALSE;
    $this->data = $data;
    if (isset($data[0]['action'])) {
      foreach ($data as $dataPoint) {
        if ($dataPoint['action'] == 'question') {
          $this->answerContext = $dataPoint;
          return 'question';
        }
        if ($dataPoint['action'] == 'information') {
          $this->questions[] = $dataPoint['conversation'] ?? $dataPoint['information'];
          return 'information';
        }
        if ($dataPoint['action'] == 'suggestion') {
          $this->questions[] = $dataPoint['conversation'] ?? $dataPoint['information'];
          return 'suggestion';
        }
        if ($dataPoint['action'] == 'manipulation') {
          $manipulation = TRUE;
        }
        if ($dataPoint['action'] == 'fail') {
          $this->questions[] = $dataPoint['conversation'] ?? $dataPoint['information'];
          return 'suggestion';
        }
      }
    }
    // Set blueprint.
    if ($data[0]['action'] == 'manipulation' && !$this->createDirectly) {
      $data[0]['action'] = 'blueprint';
    }
    if ($manipulation) {
      return 'manipulation';
    }

    return 'fail';
  }

  /**
   * Reshuffle the components.
   *
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display
   *   The display interface.
   *
   * @return bool
   *   If the reshuffle was successful.
   */
  public function reshuffleComponents(EntityFormDisplayInterface|EntityViewDisplayInterface $display) {
    $field_manager = $this->entityFieldManager;
    $entity_type_id = $display->getTargetEntityTypeId();
    $bundle = $display->getTargetBundle();
    $field_definitions = $field_manager->getFieldDefinitions($entity_type_id, $bundle);
    $extra_fields = $field_manager->getExtraFields($entity_type_id, $bundle);

    $type = $display instanceof EntityFormDisplayInterface ? 'form' : 'view';

    $componentList = '';
    $components = $display->getComponents();
    foreach ($extra_fields['display'] as $key => $field) {
      if ($field['visible'] == FALSE) {
        continue;
      }
      $components[$key] = $field;
      $components[$key]['type'] = 'extra_field';
    }

    // Reorder the components based on weight.
    uasort($components, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    foreach ($components as $component => $data) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_definition = $field_definitions[$component] ?? NULL;
      if (($field_definition && $field_definition->isDisplayConfigurable($type)) || (isset($data['type']) && $data['type'] == 'extra_field')) {
        $componentList .= 'data_name: ' . $component . ' - weight: ' . $data['weight'] . "\n";
      }
    }
    $data = $this->agentHelper->runSubAgent('determineFieldOrder', [
      'Current Field Order' => $componentList,
    ]);

    if ($data[0]['action'] == 'no_reorder' || !isset($data[0]['field_order'])) {
      return FALSE;
    }
    $set = FALSE;
    $weight = 0;
    foreach ($data[0]['field_order'] as $part) {
      if (isset($components[$part])) {
        $components[$part]['weight'] = $weight;
        $display->setComponent($part, $components[$part]);
        if ($display->save()) {
          $set = TRUE;
          $this->structuredResultData->setEditedConfig($display);
        }

        // Add some margin.
        $weight = $weight + 10;
      }
    }
    $this->structuredResultData->setEditedConfig($display);
    return $set;
  }

  /**
   * Set field settings.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundleType
   *   The bundle type.
   * @param string $fieldType
   *   The field type.
   * @param string $fieldName
   *   The field name.
   * @param string $question
   *   The question.
   * @param array $currentSettings
   *   The current settings.
   *
   * @return array
   *   The field settings as an array with instructions.
   */
  public function setFieldSettings($entityType, $bundleType, $fieldType, $fieldName, $question, $currentSettings = []) {
    $settings = $this->fieldAgentHelper->getFieldSettingsAsContext($entityType, $bundleType, $fieldType);

    $context = [
      'Available Field Settings' => $settings,
      'The current settings' => Yaml::dump($currentSettings),
    ];
    // If its an entity reference or a entity reference revisions, we need to
    // load all bundles.
    if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions'])) {
      // Check if storage settings exists.
      $storageId = $entityType . '.' . $fieldName;
      /** @var \Drupal\field\Entity\FieldStorageConfig */
      $storage = $this->entityTypeManager->getStorage('field_storage_config')->load($storageId);
      if (!is_null($storage) && $storage->getSetting('target_type')) {
        $context['Available Bundles to target'] = $this->fieldAgentHelper->getBundles($storage->getSetting('target_type'));
      }
    }
    $data = $this->agentHelper->runSubAgent('determineFieldSettings', $context, $question);
    if (!isset($data[0]['settings'])) {
      return $data[0] ?? [];
    }
    return $data[0];
  }

  /**
   * Set storage settings.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundleType
   *   The bundle type.
   * @param string $fieldType
   *   The field type.
   * @param string $question
   *   The question.
   *
   * @return array
   *   The storage settings as an array.
   */
  public function setStorageSettings($entityType, $bundleType, $fieldType, $question) {
    // Load all entity types if its a field type entity reference or entity
    // reference revisions.
    $context = [
      'Available Storage Settings' => $this->fieldAgentHelper->getStorageSettings($entityType, $bundleType, $fieldType),
    ];
    if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions'])) {
      $context['Target Types to Reference'] = $this->fieldAgentHelper->getContentEntityTypes();
    }

    $data = $this->agentHelper->runSubAgent('determineStorageSettings', $context, $question);
    if (!isset($data[0])) {
      return [];
    }
    return $data[0];
  }

}
