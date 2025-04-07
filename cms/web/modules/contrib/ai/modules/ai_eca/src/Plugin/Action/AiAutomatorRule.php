<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\Action\ConfigurableActionTrait;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\TypedData\PropertyPathTrait;
use Drupal\eca_content\Plugin\EntitySaveTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set the value of an entity field using an AI Automator.
 *
 * @Action(
 *   id = "eca_ai_automator",
 *   label = @Translation("AI Automator Trigger"),
 *   description = @Translation("Trigger a predefined AI Automator to run."),
 *   eca_version_introduced = "2.0.0",
 *   type = "entity"
 * )
 */
class AiAutomatorRule extends ActionBase implements ConfigurableInterface, PluginFormInterface, DependentPluginInterface {

  use EntitySaveTrait;
  use ConfigurableActionTrait;
  use PropertyPathTrait;
  use PluginFormTrait;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The AI Interpolator automator type.
   */
  protected AiAutomatorTypeManager $automatorType;

  /**
   * The AI Interpolator rule runner.
   */
  protected AiAutomatorRuleRunner $ruleRunner;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setAiAutomatorTypeManager($container->get('plugin.manager.ai_automator'));
    $instance->setAiAutomatorRuleRunner($container->get('ai_automator.rule_runner'));
    $instance->setFieldManager($container->get('entity_field.manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\ai_automator\Entity\AiAutomator $automator */
    $automator = $this->entityTypeManager->getStorage('ai_automator')->load($this->configuration['automator']);
    // The rule failed somehow.
    if (is_null($automator)) {
      throw new \InvalidArgumentException('The automator does not exist.');
    }
    // Check so the rule belongs to the entity.
    if ($entity->getEntityTypeId() != $automator->get('entity_type') || $entity->bundle() != $automator->get('bundle')) {
      throw new \InvalidArgumentException('The entity and the bundle does not match the rule of the automator.');
    }
    // Get field definition.
    $fieldDefinition = $this->fieldManager->getFieldDefinitions($automator->get('entity_type'), $automator->get('bundle'))[$automator->get('field_name')];
    if (!$fieldDefinition) {
      throw new \RuntimeException('No field definition found');
    }
    // Load the rule.
    $rule = $this->automatorType->createInstance($automator->get('rule'));
    if (!$rule) {
      throw new \InvalidArgumentException('No rule found');
    }
    // Prepare the config as in the normal module.
    $automatorConfig = [];
    foreach ($automator->get('plugin_config') as $key => $setting) {
      $automatorConfig[substr($key, 10)] = $setting;
    }

    $value = $entity->get($fieldDefinition->getName())->getValue();
    // Rule if overwrite is on or if empty.
    if ($this->configuration['overwrite'] || empty($rule->checkIfEmpty($value, $automatorConfig))) {
      // Run the rule.
      $this->ruleRunner->generateResponse($entity, $fieldDefinition, $automatorConfig);
    }

    // Save entity if wanted.
    if (!empty($this->configuration['save_entity'])) {
      $this->save($entity);
    }

  }

  /**
   * The save method.
   *
   * From the SetFieldValue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which might have to be saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function save(EntityInterface $entity): void {
    if (empty($entity->eca_context) || !empty($this->configuration['save_entity'])) {
      $this->saveEntity($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'automator' => '',
      'overwrite' => FALSE,
      'save_entity' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $form['automator'] = [
      '#type' => 'select',
      '#title' => $this->t('Automator'),
      '#options' => $this->getAvailableEcaRules(),
      '#weight' => -30,
      '#default_value' => $this->configuration['automator'],
    ];

    $form['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overwrite existing content.'),
      '#description' => $this->t('If checked, the existing content will be overwritten each save. This is useful when you want your ECA process to be in full control when the content is updated. If unchecked, the ECA process will only run when the field is empty.'),
      '#default_value' => $this->configuration['overwrite'],
      '#weight' => -20,
    ];

    $form['save_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save entity after interpolation.'),
      '#description' => $this->t('If checked, this rule will also take care of saving the entity.'),
      '#default_value' => $this->configuration['save_entity'],
      '#weight' => -21,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['automator'] = $form_state->getValue('automator');
    $this->configuration['overwrite'] = $form_state->getValue('overwrite');
    $this->configuration['save_entity'] = $form_state->getValue('save_entity');
  }

  /**
   * Get available ECA rules.
   *
   * @return array
   *   The available ECA rules.
   */
  protected function getAvailableEcaRules() {
    $definitions = $this->entityTypeManager->getStorage('ai_automator')->loadMultiple();
    $options = [];
    /** @var \Drupal\ai_automator\Entity\AiAutomator $definition */
    foreach ($definitions as $definition) {
      // Only show the ECA automators.
      if ($definition->get('worker_type') == 'eca') {
        $options[$definition->id()] = $definition->label() . ' (' . $definition->get('entity_type') . '/' . $definition->get('bundle') . ')';
      }
    }
    return $options;
  }

  /**
   * Set the AI Automator type manager.
   *
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager $automatorType
   *   The AI Automator type manager.
   */
  protected function setAiAutomatorTypeManager(AiAutomatorTypeManager $automatorType): void {
    $this->automatorType = $automatorType;
  }

  /**
   * Set the AI Automator rule runner.
   *
   * @param \Drupal\ai_automators\AiAutomatorRuleRunner $ruleRunner
   *   The AI Automator rule runner.
   */
  protected function setAiAutomatorRuleRunner(AiAutomatorRuleRunner $ruleRunner): void {
    $this->ruleRunner = $ruleRunner;
  }

  /**
   * Set the field manager.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   */
  protected function setFieldManager(EntityFieldManagerInterface $fieldManager): void {
    $this->fieldManager = $fieldManager;
  }

}
