<?php

namespace Drupal\ai_agents\Service\FieldAgent;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_agents\Exception\AgentPermissionsException;
use Drupal\ai_agents\Exception\AgentProcessingException;
use Drupal\ai_agents\Exception\AgentValidationException;
use Symfony\Component\Yaml\Yaml;

/**
 * The field agent helper.
 */
class FieldAgentHelper {

  /**
   * FieldAgentHelper constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypePluginManager
   *   The field type plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetPluginManager
   *   The widget plugin manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatterPluginManager
   *   The formatter plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected WidgetPluginManager $widgetPluginManager,
    protected FormatterPluginManager $formatterPluginManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected RouteProviderInterface $routeProvider,
  ) {
  }

  /**
   * Check so the user has access to manipulate the field of an entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @throws \Drupal\ai_agents\Exception\AgentPermissionsException
   *   If the user does not have access.
   */
  public function checkAdministerFieldPermissions($entityType) {
    if (!$this->currentUser->hasPermission('administer ' . $entityType . ' fields')) {
      throw new AgentPermissionsException("You do not have permissions to change fields on the entity type $entityType");
    }
  }

  /**
   * Validate the array for manipulation.
   *
   * @param array $data
   *   The data.
   *
   * @throws \Drupal\ai_agents\Exception\AgentValidationException
   *   If the data is not valid.
   *
   * @return array
   *   The cleaned data.
   */
  public function validateAndCleanManipulationDataCreateEdit(array $data) {
    if (!isset($data['entity_type']) || !isset($data['bundle_name']) || !isset($data['field_name'])) {
      throw new AgentValidationException('The data is not valid.');
    }
    // The field_name should max be 32 chars.
    if (strlen($data['field_name']) > 32) {
      $data['field_name'] = substr($data['field_name'], 0, 32);
    }

    return $data;
  }

  /**
   * Check if the field config already exists.
   *
   * @param string $data
   *   The array data.
   *
   * @return null|\Drupal\field\Entity\FieldConfig
   *   The field config or null.
   */
  public function fieldConfigExists(array $data) {
    $configId = $data['entity_type'] . '.' . $data['bundle_name'] . '.' . $data['field_name'];
    if ($fieldConfig = $this->entityTypeManager->getStorage('field_config')->load($configId)) {
      return $fieldConfig;
    }
    return NULL;
  }

  /**
   * Check if the field storage config already exists.
   *
   * @param array $data
   *   The array data.
   *
   * @return null|\Drupal\field\Entity\FieldStorageConfig
   *   The field storage config or null.
   */
  public function fieldStorageConfigExists(array $data) {
    $configId = $data['entity_type'] . '.' . $data['field_name'];
    if ($fieldStorageConfig = $this->entityTypeManager->getStorage('field_storage_config')->load($configId)) {
      return $fieldStorageConfig;
    }
    return NULL;
  }

  /**
   * Gets the extra field information.
   *
   * @param array $data
   *   The data array.
   *
   * @return string
   *   The extra field information.
   */
  public function fieldInformation(array $data) {
    $fields = $this->entityFieldManager->getFieldDefinitions($data['entity_type'], $data['bundle_name']);
    $field_name = $data['field_name'];
    $fieldInformation = "";
    if (isset($fields[$field_name])) {
      $field = $fields[$field_name];
      $fieldType = $field->getType();
      $fieldInformation = "Field Name: $field_name\nField Type: $fieldType\n";
      $fieldInformation .= "Field Required: " . ($field->isRequired() ? 'Yes' : 'No') . "\n";
      $fieldInformation .= "Field Cardinality: " . $field->getFieldStorageDefinition()->getCardinality() . "\n";
      $fieldInformation .= "Field Translatable: " . ($field->isTranslatable() ? 'Yes' : 'No') . "\n";
      $fieldInformation .= "Field Description: " . $field->getDescription() . "\n";
    }
    return $fieldInformation;
  }

  /**
   * Determine form widgets for field type.
   *
   * @param string $fieldType
   *   The field type.
   * @param string $fieldName
   *   The field name.
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The form widgets.
   */
  public function determineFormWidgetsForFieldType($fieldType, $fieldName, $entityType, $bundle) {
    $widgets = $this->widgetPluginManager->getDefinitions();
    $fieldWidgets = '';
    foreach ($widgets as $pluginId => $definition) {
      // Check if this formatter is applicable to the desired field type.
      if (in_array($fieldType, $definition['field_types'])) {
        // Some fail to load, so we need to catch that.
        try {
          $fieldDefinition = $this->entityTypeManager
            ->getStorage('field_config')
            ->create([
              'field_name' => $fieldName,
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'label' => 'Custom Field',
              'description' => 'A custom field for demonstration purposes.',
              'required' => FALSE,
              'translatable' => FALSE,
              'default_value' => [],
              'settings' => [],
              'field_type' => $fieldType,
            ]);
          // If its empty its a base field definition.
          if (!$fieldDefinition) {
            $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
            foreach ($fields as $field) {
              if ($field->getName() == $fieldName) {
                $fieldDefinition = $field;
              }
            }
          }
          $instance = $this->widgetPluginManager->createInstance($pluginId, [
            'field_definition' => $fieldDefinition,
            'view_mode' => 'default',
            'settings' => [],
            'third_party_settings' => [],
          ]);
          $settings = $instance->defaultSettings();
          // To get some more context from the form, if possible.
          $form = $instance->settingsForm([], new FormState());
          $extraSettings = [];
          foreach ($settings as $key => $setting) {
            $this->extractSettingsFromForm($key, $setting, $form, $extraSettings);
          }
          $widgetSettings = Yaml::dump($extraSettings);
        }
        catch (\Exception $e) {
          continue;
        }
        $fieldWidgets .= "Field widget data name: $pluginId, Field widget readable name: " . $definition['label'] . "\n";
        $fieldWidgets .= "Settings available as JSON:\n";
        $fieldWidgets .= $widgetSettings . "\n";
        $fieldWidgets .= "------------------------------\n\n";
      }
    }
    return $fieldWidgets;
  }

  /**
   * Get the list of view modes for a field type.
   *
   * @param string $fieldType
   *   The field type.
   *
   * @return array
   *   The view modes.
   */
  public function getAvailableViewFormatsForFieldType($fieldType) {
    // Get all available formatters.
    $formatters = $this->formatterPluginManager->getDefinitions();
    $fieldFormatters = [];
    foreach ($formatters as $pluginId => $definition) {
      // Check if this formatter is applicable to the desired field type.
      if (in_array($fieldType, $definition['field_types'])) {
        $fieldFormatters[] = $pluginId;
      }
    }
    return $fieldFormatters;
  }

  /**
   * Figure out possible view formats for a field type.
   *
   * @param string $fieldType
   *   The field type.
   * @param string $fieldName
   *   The field name.
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The view formats.
   */
  public function determineViewFormatsForFieldType($fieldType, $fieldName, $entityType, $bundle) {
    // Get all available formatters.
    $formatters = $this->formatterPluginManager->getDefinitions();
    $fieldFormatters = '';
    foreach ($formatters as $pluginId => $definition) {
      // Check if this formatter is applicable to the desired field type.
      if (in_array($fieldType, $definition['field_types'])) {
        // Some fail to load, so we need to catch that.
        try {
          $fieldDefinition = $this->entityTypeManager
            ->getStorage('field_config')
            ->create([
              'field_name' => $fieldName,
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'label' => 'Custom Field',
              'description' => 'A custom field for demonstration purposes.',
              'required' => FALSE,
              'translatable' => FALSE,
              'default_value' => [],
              'settings' => [],
              'field_type' => $fieldType,
            ]);
          $instance = $this->formatterPluginManager->createInstance($pluginId, [
            'field_definition' => $fieldDefinition,
            'label' => 'Custom Field',
            'view_mode' => 'default',
            'settings' => [],
            'third_party_settings' => [],
          ]);
          $settings = $instance->defaultSettings();
          // To get some more context from the form, if possible.
          $form = $instance->settingsForm([], new FormState());
          $extraSettings = [];
          foreach ($settings as $key => $setting) {
            $this->extractSettingsFromForm($key, $setting, $form, $extraSettings);
          }
          $formatterSettings = Yaml::dump($extraSettings);
        }
        catch (\Exception $e) {
          continue;
        }
        $fieldFormatters .= "Formatter data name: $pluginId\nFormatter readable name: " . $definition['label'] . "\n";
        $fieldFormatters .= "Settings available as JSON:\n";
        $fieldFormatters .= $formatterSettings . "\n";
        $fieldFormatters .= "------------------------------\n\n";
      }
    }
    return $fieldFormatters;
  }

  /**
   * Store the storage settings entity.
   *
   * @param array $data
   *   The data array.
   * @param array $settings
   *   The storage settings.
   *
   * @throws \Drupal\ai_agents\Exception\AgentProcessingException
   *   If the storage settings could not be saved.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   The storage settings entity.
   */
  public function storeFieldStorage(array $data, array $settings) {
    $storageSettingsEntity = $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => $data['field_name'],
      'entity_type' => $data['entity_type'],
      'type' => $data['field_type'],
      'translatable' => $data['translatable'],
      'cardinality' => $data['cardinality'] ?? 1,
      'persist_with_no_fields' => FALSE,
      'custom_storage' => FALSE,
      'settings' => $settings,
    ]);
    if ($storageSettingsEntity->save()) {
      return $storageSettingsEntity;
    }
    else {
      throw new AgentProcessingException('Could not save the storage settings entity.');
    }
  }

  /**
   * Store a default form widget.
   *
   * @param array $data
   *   The data array.
   *
   * @return \Drupal\Core\Entity\Entity\EntityFormDisplay
   *   The form widget entity.
   */
  public function storeDefaultFormWidget(array $data) {
    $fieldTypes = $this->getFieldTypes();
    // Define the form widget.
    $formDisplay = $this->getDefaultFormDisplay($data);
    $formType = $fieldTypes[$data['field_type']]['default_widget'];
    // Default config.
    $form_config = [
      'type' => $formType,
      // Default to 50, so its easy to change up and down.
      'weight' => 50,
    ];
    $formDisplay->setComponent($data['field_name'], $form_config);
    if ($formDisplay->save()) {
      return $formDisplay;
    }
    throw new AgentProcessingException('Could not save the form widget entity.');
  }

  /**
   * Get the default form display.
   *
   * @param array $data
   *   The data array.
   *
   * @return \Drupal\Core\Entity\Entity\EntityFormDisplay
   *   The form display entity.
   */
  public function getDefaultFormDisplay(array $data) {
    $formDisplay = $this->entityDisplayRepository->getFormDisplay($data['entity_type'], $data['bundle_name'], 'default');
    return $formDisplay;
  }

  /**
   * Gets the form display for a field.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   The component.
   */
  public function getFormDisplay(array $data) {
    $formDisplay = $this->entityDisplayRepository->getFormDisplay($data['entity_type'], $data['bundle_name'], 'default');
    return $formDisplay->getComponent($data['field_name']);
  }

  /**
   * Store a default display widget.
   *
   * @param array $data
   *   The data array.
   *
   * @return \Drupal\Core\Entity\Entity\EntityViewDisplay
   *   The display widget entity.
   */
  public function storeDefaultDisplayWidget(array $data) {
    $fieldTypes = $this->getFieldTypes();
    // Define the display widget.
    $display = $this->getDefaultDisplayWidget($data);
    $display->setComponent($data['field_name'], [
      'label' => 'above',
      'type' => $fieldTypes[$data['field_type']]['default_formatter'],
      // Set default to weight.
      'weight' => 50,
    ]);
    if ($display->save()) {
      return $display;
    }
    throw new AgentProcessingException('Could not save the display widget entity.');
  }

  /**
   * Get the default view display.
   *
   * @param array $data
   *   The data array.
   *
   * @return \Drupal\Core\Entity\Entity\EntityViewDisplay
   *   The view display entity.
   */
  public function getDefaultDisplayWidget(array $data) {
    $viewDisplay = $this->entityDisplayRepository->getViewDisplay($data['entity_type'], $data['bundle_name'], 'default');
    return $viewDisplay;
  }

  /**
   * Gets the view display for a field.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   The component.
   */
  public function getViewDisplay(array $data) {
    $viewDisplay = $this->entityDisplayRepository->getViewDisplay($data['entity_type'], $data['bundle_name'], 'default');
    return $viewDisplay->getComponent($data['field_name']);
  }

  /**
   * Store the field config entity.
   *
   * @param array $data
   *   The data array.
   * @param array $settings
   *   The field settings.
   *
   * @throws \Drupal\ai_agents\Exception\AgentProcessingException
   *   If the field config could not be saved.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   The field config entity.
   */
  public function storeFieldConfig(array $data, array $settings) {
    // It can be edit as well.
    /** @var \Drupal\field\Entity\FieldConfig $config */
    $config = $this->entityTypeManager->getStorage('field_config')->load($data['entity_type'] . '.' . $data['bundle_name'] . '.' . $data['field_name']);
    $edit = TRUE;
    if (!$config) {
      $config = $this->entityTypeManager->getStorage('field_config')->create([
        'field_name' => $data['field_name'],
        'entity_type' => $data['entity_type'],
        'bundle' => $data['bundle_name'] ?? '',
      ]);
      $edit = FALSE;
    }
    $config->set('label', $data['label']);
    // Only edit if it gives back something.
    if (!$edit || !empty($data['description'])) {
      $config->set('description', $data['description']);
    }
    $config->set('required', $data['required']);
    $config->set('translatable', $data['translatable']);
    $config->set('default_value', []);
    // Only update settings, when it has a value.
    if ($data['manipulation'] == 'create' || ($data['manipulation'] == 'edit' && !empty($settings))) {
      $config->set('settings', $settings);
    }
    // If its a new field, set the field type.
    if ($data['manipulation'] == 'create') {
      $config->set('field_type', $data['field_type']);
    }
    if ($config->save()) {
      return $config;
    }
    else {
      throw new AgentProcessingException('Could not save the field config entity.');
    }
  }

  /**
   * Get the default storage settings.
   *
   * @param string $entityType
   *   The field type.
   * @param string $bundleType
   *   The bundle type.
   * @param string $fieldType
   *   The field type.
   *
   * @return string
   *   The storage settings as a string.
   */
  public function getStorageSettings($entityType, $bundleType, $fieldType) {
    $fieldStorageDefinition = BaseFieldDefinition::create($fieldType)
      ->setLabel('Custom Field')
      ->setDescription('A custom field for demonstration purposes.')
      ->setSettings([])
      ->setCardinality(1)
      ->setRequired(FALSE);
    $instance = $this->fieldTypePluginManager->createInstance($fieldType, [
      'field_definition' => $fieldStorageDefinition,
      'field_name' => 'field_custom',
      'name' => 'Field Custom',
      'parent' => NULL,
      'bundle' => $bundleType,
      'entity_type' => $entityType,
    ]);
    $settings = $instance->defaultStorageSettings();
    $form = [];
    $element = $instance->storageSettingsForm($form, new FormState(), FALSE);

    $extraSettings = [];
    foreach ($settings as $key => $setting) {
      $this->extractSettingsFromForm($key, $setting, $element, $extraSettings);
    }
    return Yaml::dump($extraSettings);
  }

  /**
   * Get the default config settings.
   *
   * @param string $entityType
   *   The field type.
   * @param string $bundleType
   *   The bundle type.
   * @param string $fieldType
   *   The field type.
   *
   * @return string
   *   The config settings as a string.
   */
  public function getFieldSettingsAsContext($entityType, $bundleType, $fieldType) {
    // Special case for entity reference.
    if ($fieldType == 'entity_reference' || $fieldType == 'entity_reference_revisions') {
      $extraSettings = [
        'handler' => [
          'type' => 'string',
          'description' => 'The handler to use for the entity reference field using default:{entity_type}.',
        ],
        'handler_settings' => [
          'target_bundles' => [
            'type' => 'array',
            'description' => 'The target bundles for the entity reference field with the id as both key and value.',
          ],
          'auto_create' => [
            'type' => 'boolean',
            'description' => 'If the entity reference field should auto create entities. Only used for taxonomy terms.',
          ],
        ],
      ];
    }
    else {
      $fieldStorageDefinition = BaseFieldDefinition::create($fieldType)
        ->setLabel('Custom Field')
        ->setDescription('A custom field for demonstration purposes.')
        ->setSettings([])
        ->setCardinality(1)
        ->setRequired(FALSE);
      $instance = $this->fieldTypePluginManager->createInstance($fieldType, [
        'field_definition' => $fieldStorageDefinition,
        'parent' => NULL,
        'field_name' => 'field_custom',
        'name' => 'Field Custom',
        'bundle' => $bundleType,
        'entity_type' => $entityType,
      ]);
      $settings = $instance->defaultFieldSettings();
      if ($fieldType != 'entity_reference' && $fieldType != 'entity_reference_revisions') {
        $form = [];
        $element = $instance->fieldSettingsForm($form, new FormState(), FALSE);
      }
      else {
        $element = [];
      }

      $form = [];
      $element = $instance->fieldSettingsForm($form, new FormState(), FALSE);

      $extraSettings = [];
      foreach ($settings as $key => $setting) {
        $this->extractSettingsFromForm($key, $setting, $element, $extraSettings);
      }
    }
    // Output it as YAML for the LLM.
    return Yaml::dump($extraSettings);
  }

  /**
   * Convert translatable markup to string.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   The form without translatable markup.
   */
  public function readableForm(array $form): array {
    foreach ($form as $key => $value) {
      if (is_array($value)) {
        $form[$key] = $this->readableForm($value);
      }
      elseif ($value instanceof TranslatableMarkup) {
        $form[$key] = (string) $value;
      }
    }
    return $form;
  }

  /**
   * Extract information from a default settings and form recursively.
   *
   * @param array $key
   *   The settings key.
   * @param array $value
   *   The settings value.
   * @param array $form
   *   The form.
   * @param array $extraSettings
   *   Extra settings.
   */
  public function extractSettingsFromForm($key, $value, $form, &$extraSettings = []) {
    if (is_array($value) && isset($form[$key])) {
      foreach ($value as $subKey => $subValue) {
        $originalSettings = $extraSettings;
        $this->extractSettingsFromForm($subKey, $subValue, $form[$key], $extraSettings);
        // Make a difference between the original and the new settings keys.
        $diff = array_diff_key($extraSettings, $originalSettings);
        if (!empty($diff)) {
          foreach ($diff as $diffKey => $diffValue) {
            $extraSettings[$key][$diffKey] = $diffValue;
            unset($extraSettings[$diffKey]);
          }
        }
      }
    }
    else {
      $extraSettings[$key]['default'] = $value;
      if (isset($form[$key])) {
        if (isset($form[$key]['#description']) && (is_string($form[$key]['#description']) || $form[$key]['#description'] instanceof TranslatableMarkup)) {
          $extraSettings[$key]['description'] = (string) $form[$key]['#description'];
        }
        if (isset($form[$key]['#title'])) {
          $extraSettings[$key]['label'] = (string) $form[$key]['#title'];
        }
        if (isset($form[$key]['#options']) && is_array($form[$key]['#options']) && count($form[$key]['#options'])) {
          foreach ($form[$key]['#options'] as $optionKey => $optionValue) {
            $extraSettings[$key]['options'][$optionKey] = is_array($optionValue) ? $optionValue : (string) $optionValue;
          }
        }
      }
    }
  }

  /**
   * Get field type list for prompt.
   *
   * @return string
   *   The field type list.
   */
  public function getFieldTypesList() {
    $fieldTypes = $this->getFieldTypes();
    $list = "";
    foreach ($fieldTypes as $fieldTypeId => $fieldType) {
      $description = is_array($fieldType['description']) ? implode(". ", $fieldType['description']) : $fieldType['description'];
      $list .= '* ' . $fieldType['label'] . ' (field_type: ' . $fieldTypeId . ') - ' . $description . "\n";
    }
    return $list;
  }

  /**
   * Get all field types possible.
   *
   * @return array
   *   The field types.
   */
  public function getFieldTypes() {
    $fieldTypes = $this->fieldTypePluginManager->getDefinitions();
    $fieldInfo = [];
    foreach ($fieldTypes as $fieldTypeId => $definition) {
      if (isset($definition['label']) && isset($definition['description'])) {
        $fieldInfo[$fieldTypeId] = [
          'label' => $definition['label'],
          'description' => $definition['description'],
          'default_widget' => $definition['default_widget'] ?? '',
          'default_formatter' => $definition['default_formatter'] ?? '',
        ];
      }
    }
    return $fieldInfo;
  }

  /**
   * Get all entity types and bundles.
   *
   * @return string
   *   A list of all the entity types and bundles.
   */
  public function getEntityTypesAndBundles() {
    $entityTypes = $this->entityTypeManager->getDefinitions();
    $list = '';
    foreach ($entityTypes as $entityTypeId => $entityType) {
      // Get all bundles of the entity type (if the exist).
      if ($entityType->getBundleEntityType()) {
        $bundles = $this->entityTypeManager->getStorage($entityType->getBundleEntityType())->loadMultiple();
        foreach ($bundles as $bundle) {
          $list .= '* ' . $entityType->getLabel() . ' (data_name: ' . $entityTypeId . ') - ' . $bundle->label() . ' (bundle_data_name: ' . $bundle->id() . ')' . "\n";
        }
      }
    }
    return $list;
  }

  /**
   * Get all entity types and bundles and their fields.
   */

  /**
   * Get all the fields for and entity and bundle combination.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundleType
   *   The bundle type.
   *
   * @return string
   *   The list of fields.
   */
  public function getEntityFields($entityType, $bundleType) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundleType);
    $list = '';
    foreach ($fields as $fieldId => $field) {
      $list .= 'Field Readable Name: ' . $field->getLabel() . "\n";
      $list .= 'Field Type: ' . $field->getType() . "\n";
      $list .= 'Field Machine Name: ' . $fieldId . "\n\n";
    }
    return $list;
  }

  /**
   * Figure out the field type for a field.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The field type.
   */
  public function getFieldType($fieldName, $entityType, $bundle) {
    // Get the field type for the field and entity combination.
    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $fieldType = '';
    foreach ($fields as $field) {
      if ($field->getName() == $fieldName) {
        $fieldType = $field->getType();
      }
    }
    return $fieldType;
  }

  /**
   * Get all entity types.
   *
   * @return string
   *   The entity types as string.
   */
  public function getContentEntityTypes() {
    $entityTypes = $this->entityTypeManager->getDefinitions();
    $contentEntityTypes = "";
    foreach ($entityTypes as $entityTypeId => $entityType) {
      if ($entityType->entityClassImplements(ContentEntityInterface::class)) {
        $contentEntityTypes .= 'data name: ' . $entityTypeId . ', label: ' . $entityType->getLabel() . "\n";
      }
    }
    return $contentEntityTypes;
  }

  /**
   * Get all the bundles for an entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return string
   *   The bundles as a string.
   */
  public function getBundles($entityType) {
    // Get all the bundles for the entity type.
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityType);
    $bundleList = '';
    foreach ($bundles as $bundle => $bundleInfo) {
      $bundleList .= 'data name: ' . $bundle . ', label: ' . $bundleInfo['label'] . "\n";
    }
    return $bundleList;
  }

  /**
   * Check if entity type and bundle exists.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   */
  public function checkIfEntityAndBundleExists($entityType, $bundle) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityType);
    if (!isset($bundles[$bundle])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get a route arguments for a bundle.
   *
   * @param string $route
   *   The route.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $extraArguments
   *   Extra arguments to fill in.
   *
   * @return array
   *   The arguments filled out
   */
  public function getRouteArguments($route, $entity_type, $bundle, $extraArguments = []) {
    $arguments = [
      'entity_type' => $entity_type,
    ];
    $routeData = $this->routeProvider->getRouteByName($route);
    $parameters = $routeData->compile()->getPathVariables();
    if (isset($parameters[0])) {
      $arguments[$parameters[0]] = $bundle;
    }
    // Merge the extra arguments.
    $arguments = array_merge($arguments, $extraArguments);
    return $arguments;
  }

}
