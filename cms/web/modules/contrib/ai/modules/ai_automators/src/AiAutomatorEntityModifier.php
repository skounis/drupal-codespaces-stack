<?php

namespace Drupal\ai_automators;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Event\AutomatorConfigEvent;
use Drupal\ai_automators\Event\ProcessFieldEvent;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper for entity saving logic.
 */
class AiAutomatorEntityModifier {

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The process manager.
   */
  protected AiAutomatorFieldProcessManager $processes;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an entity modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\ai_automators\AiFieldRules $aiFieldRules
   *   The field rules.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiAutomatorFieldProcessManager $processes, AiFieldRules $aiFieldRules, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager) {
    $this->fieldManager = $fieldManager;
    $this->processes = $processes;
    $this->fieldRules = $aiFieldRules;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Field form should have form altered to allow automatic content generation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param bool $isInsert
   *   Is it an insert.
   *
   * @return bool
   *   If the entity is saved or marked for saving.
   */
  public function saveEntity(EntityInterface $entity, $isInsert = FALSE) {
    // Only run on Content Interfaces.
    if (!($entity instanceof ContentEntityInterface)) {
      return FALSE;
    }
    // Get and check so field configs exists.
    $configs = $this->entityHasConfig($entity);
    if (!count($configs)) {
      return FALSE;
    }

    // Resort on weight to create in the right order.
    usort($configs, function ($a, $b) {
      if ($a['automatorConfig']['weight'] > $b['automatorConfig']['weight']) {
        return 1;
      }
      elseif ($a['automatorConfig']['weight'] < $b['automatorConfig']['weight']) {
        return -1;
      }
      return 0;
    });

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processes->getDefinitions() as $definition) {
      $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
    }

    // Get process for this entity.
    $processes = $this->getProcesses($configs);

    // Preprocess.
    foreach ($processes as $process) {
      $process->preProcessing($entity);
    }

    // Walk through the fields and check if we need to save anything.
    foreach ($configs as $config) {
      // Event where you can change the field configs when something exists.
      if (!empty($config['automatorConfig'])) {
        $event = new AutomatorConfigEvent($entity, $config['automatorConfig']);
        $this->eventDispatcher->dispatch($event, AutomatorConfigEvent::EVENT_NAME);
        $config['automatorConfig'] = $event->getAutomatorConfig();
      }
      // Load the processor or load direct.
      $processor = $processes[$config['automatorConfig']['worker_type']] ?? $processes['direct'];
      if (method_exists($processor, 'isImport') && $isInsert) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['automatorConfig'], $processor);
      }
      if (!method_exists($processor, 'isImport') && !$isInsert && $config['fieldDefinition']) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['automatorConfig'], $processor);
      }
    }

    // Postprocess.
    foreach ($processes as $process) {
      $process->postProcessing($entity);
    }
    return TRUE;
  }

  /**
   * Checks if an entity has fields with automator enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   *
   * @return array
   *   An array with the field configs affected.
   */
  public function entityHasConfig(EntityInterface $entity) {
    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $fields = $storage->loadByProperties([
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ]);
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $fieldConfigs = [];
    $automatorConfig = [];
    /** @var \Drupal\ai_automators\Entity\AiAutomator $field */
    foreach ($fields as $field) {
      // Check if enabled and return the config.
      $fieldConfigs[$field->id()]['fieldDefinition'] = $fieldDefinitions[$field->get('field_name')];
      $automatorConfig = [
        'field_name' => $field->get('field_name'),
      ];
      foreach ($field->get('plugin_config') as $key => $setting) {
        $automatorConfig[substr($key, 10)] = $setting;
      }
      $fieldConfigs[$field->id()]['automatorConfig'] = $automatorConfig;
    }

    return $fieldConfigs;
  }

  /**
   * Gets the processes available.
   *
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   Array of processes keyed by id.
   */
  public function getProcesses(array $configs) {
    // Get possible processes.
    $processes = [];
    foreach ($configs as $config) {
      $definition = $this->processes->getDefinition($config['automatorConfig']['worker_type']);
      $processes[$definition['id']] = $this->processes->createInstance($definition['id']);
    }
    return $processes;
  }

  /**
   * Checks if a field should be saved and saves it appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The OpenAI Automator settings for the field.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface $processor
   *   The processor.
   *
   * @return bool
   *   If the saving was successful or not.
   */
  protected function markFieldForProcessing(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, AiAutomatorFieldProcessInterface $processor) {
    // Event to modify if the field should be processed.
    $event = new ProcessFieldEvent($entity, $fieldDefinition, $automatorConfig);
    $this->eventDispatcher->dispatch($event, ProcessFieldEvent::EVENT_NAME);
    // If a force reject or force process exists, we do that.
    if (in_array(ProcessFieldEvent::FIELD_FORCE_SKIP, $event->actions)) {
      return FALSE;
    }
    elseif (in_array(ProcessFieldEvent::FIELD_FORCE_PROCESS, $event->actions)) {
      return $processor->modify($entity, $fieldDefinition, $automatorConfig);
    }

    // Otherwise continue as normal.
    if ((!isset($automatorConfig['mode']) || $automatorConfig['mode'] == 'base') && !$this->baseShouldSave($entity, $automatorConfig)) {
      return FALSE;
    }
    elseif (isset($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && !$this->tokenShouldSave($entity, $automatorConfig)) {
      return FALSE;
    }

    return $processor->modify($entity, $fieldDefinition, $automatorConfig);
  }

  /**
   * If token mode, check if it should run.
   */
  private function tokenShouldSave(ContentEntityInterface $entity, array $automatorConfig) {
    // Get rule.
    $rule = $this->fieldRules->findRule($automatorConfig['rule']);
    // Check if a value exists.
    $value = $entity->get($automatorConfig['field_name'])->getValue();
    $value = $rule->checkIfEmpty($value, $automatorConfig);

    // Get prompt.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * If base mode, check if it should run.
   */
  private function baseShouldSave(ContentEntityInterface $entity, array $automatorConfig) {
    // Check if a value exists.
    $value = $entity->get($automatorConfig['field_name'])->getValue();

    $original = isset($entity->original) && json_encode($entity->original->get($automatorConfig['base_field'])->getValue()) ?? NULL;
    $change = json_encode($entity->get($automatorConfig['base_field'])->getValue()) !== $original;

    // Get the rule to check the value.
    $rule = $this->fieldRules->findRule($automatorConfig['rule']);
    $value = $rule->checkIfEmpty($value, $automatorConfig);

    // If the base field is not filled out.
    if (!empty($value) && !empty($value[0])) {
      return FALSE;
    }
    // If the value exists and we don't have edit mode, we do nothing.
    if (!empty($value) && !empty($value[0]) && !$automatorConfig['edit_mode']) {
      return FALSE;
    }
    // Otherwise look for a change.
    if ($automatorConfig['edit_mode'] && !$change && !empty($value) && !empty($value[0])) {
      return FALSE;
    }
    return TRUE;
  }

}
