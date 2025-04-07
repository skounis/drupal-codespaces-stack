<?php

namespace Drupal\ai_agents\PluginBase;

use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_agents\Output\StructuredResultData;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Service\AgentHelper;
use Drupal\ai_agents\Task\Task;
use Drupal\ai_agents\Task\TaskInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Helper for worker agents.
 */
abstract class AiAgentBase extends PluginBase implements AiAgentInterface, ContainerFactoryPluginInterface {

  // All should be translatable.
  use StringTranslationTrait;

  /**
   * The ai provider.
   *
   * @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   */
  protected $aiProvider;

  /**
   * The state of the agent.
   *
   * @var array
   *   The state.
   */
  protected $state;

  /**
   * The model name.
   *
   * @var string
   */
  protected $modelName;

  /**
   * The ai configuration.
   *
   * @var array
   *   The ai configuration.
   */
  protected $aiConfiguration = [];

  /**
   * The original configurations.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The original configurations.
   */
  protected $originalConfigurations;

  /**
   * The original configurations array.
   *
   * @var array
   *   The original configurations array.
   */
  protected $originalConfigurationsArray;

  /**
   * The original entities.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   *   The original entities.
   */
  protected $originalEntities;

  /**
   * The task.
   *
   * @var \Drupal\ai_agents\Task\Task
   */
  protected Task $task;

  /**
   * Create directly (or give a Blueprint)
   *
   * @var bool
   */
  protected $createDirectly = FALSE;

  /**
   * Unique runner id.
   *
   * @var string
   */
  protected $runnerId = '';

  /**
   * The structured result data.
   *
   * @var \Drupal\ai_agents\Output\StructuredResultDataInterface
   */
  protected $structuredResultData;

  /**
   * The user interface interacting with the agent.
   *
   * @var string
   */
  protected $userInterface;

  /**
   * The extra tags to send on prompts.
   *
   * @var array
   */
  protected $extraTags = [];

  /**
   * The full data of the initial task.
   *
   * @var array
   */
  protected $data;

  /**
   * Questions to ask.
   *
   * @var array
   */
  protected $questions;

  /**
   * The information to give back.
   *
   * @var string
   */
  protected $information = 'No information available.';

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AgentHelper $agentHelper,
    protected FileSystem $fileSystem,
    protected ConfigFactoryInterface $config,
    protected AccountProxyInterface $currentUser,
    protected ExtensionPathResolver $extensionPathResolver,
    protected PromptJsonDecoderInterface $promptJsonDecoder,
    protected AiProviderPluginManager $aiProviderPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Set to defaults if exist.
    $default = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat_with_complex_json');
    if (!empty($default['provider_id']) && !empty($default['model_id'])) {
      $this->aiProvider = $this->aiProviderPluginManager->createInstance($default['provider_id']);
      $this->modelName = $default['model_id'];
    }
    // Load configuration if its missing.
    if (empty($this->configuration)) {
      $this->loadConfiguration();
    }
    // Set up a new output result.
    $this->structuredResultData = new StructuredResultData();
  }

  /**
   * {@inheritDoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_agents.agent_helper'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('extension.path.resolver'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritDoc}
   */
  public function getModuleName() {
    return $this->pluginDefinition['provider'];
  }

  /**
   * {@inheritDoc}
   */
  public function isAvailable() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function isNotAvailableMessage() {
    return $this->t('Nothing');
  }

  /**
   * {@inheritDoc}
   */
  public function askQuestion() {
    return implode("\n", $this->questions);
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess() {
    if ($this->currentUser->id() == 1) {
      return AccessResult::allowed();
    }
    $config = $this->config->get('ai_agents.settings')->get('agent_settings')[$this->getid()] ?? [];
    $roles = [];
    if (!empty($config['permissions'])) {
      foreach ($config['permissions'] as $permission => $set) {
        if ($set) {
          $roles[] = $permission;
        }
      }
    }
    if (empty($roles)) {
      return AccessResult::allowed();
    }
    foreach ($this->currentUser->getRoles() as $role) {
      if (in_array($role, $roles)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritDoc}
   */
  public function getAiProvider() {
    return $this->aiProvider;
  }

  /**
   * {@inheritDoc}
   */
  public function setAiProvider($aiProvider) {
    $this->aiProvider = $aiProvider;
  }

  /**
   * {@inheritDoc}
   */
  public function getModelName() {
    return $this->modelName;
  }

  /**
   * {@inheritDoc}
   */
  public function setModelName($modelName) {
    $this->modelName = $modelName;
  }

  /**
   * {@inheritDoc}
   */
  public function getAiConfiguration() {
    return $this->aiConfiguration;
  }

  /**
   * {@inheritDoc}
   */
  public function setAiConfiguration(array $aiConfiguration) {
    $this->aiConfiguration = $aiConfiguration;
  }

  /**
   * {@inheritDoc}
   */
  public function getTask() {
    return $this->task;
  }

  /**
   * {@inheritDoc}
   */
  public function setTask(TaskInterface $task) {
    $this->task = $task;
  }

  /**
   * {@inheritDoc}
   */
  public function determineSolvability() {
    // Setup the helper runner.
    $this->agentHelper->setupRunner($this);
    return AiAgentInterface::JOB_SOLVABLE;
  }

  /**
   * {@inheritDoc}
   */
  public function solve() {
    // Setup the helper runner.
    $this->agentHelper->setupRunner($this);
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function inform() {
    return $this->information;
  }

  /**
   * {@inheritDoc}
   */
  public function getCreateDirectly() {
    return $this->createDirectly;
  }

  /**
   * {@inheritDoc}
   */
  public function setCreateDirectly($createDirectly) {
    $this->createDirectly = $createDirectly;
  }

  /**
   * {@inheritDoc}
   */
  public function getStructuredOutput(): StructuredResultData {
    return $this->structuredResultData;
  }

  /**
   * {@inheritDoc}
   */
  public function setUserInterface($userInterface, array $extraTags = []) {
    $this->userInterface = $userInterface;
    $this->extraTags = $extraTags;
  }

  /**
   * {@inheritDoc}
   */
  public function getExtraTags() {
    return $this->extraTags;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

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
    $this->data[] = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
    $this->data[0]['action'] = 'create';
  }

  /**
   * Use the structure result data.
   *
   * @param string $mode
   *   Mode can be create, edit or delete config or content.
   * @param string $configId
   *   The config id.
   * @param array $extraData
   *   Extra data.
   *
   * @return \Drupal\ai_agents\Output\StructuredResultDataInterface
   *   The structured result data.
   */

  /**
   * The rollback logic.
   */
  public function rollback() {
    // Delete all created configurations.
    foreach ($this->structuredResultData->getCreatedConfigs() as $config) {
      // Check if config type.
      $configs = explode(':', $config['config_id']);
      $config_entity = $this->entityTypeManager->getStorage($configs[0])->load($configs[1]);
      if ($config_entity) {
        $config_entity->delete();
      }
    }
    // Revert all edited configurations.
    foreach ($this->structuredResultData->getEditedConfigs() as $config) {
      $configs = explode(':', $config['config_id']);
      $config_entity = $this->entityTypeManager->getStorage($configs[0])->load($configs[1]);
      $configDependencyName = $config_entity->getConfigDependencyName();
      if (isset($this->originalConfigurations[$configDependencyName])) {
        $this->originalConfigurations[$configDependencyName]->save();
      }
    }
    // Revert all created entities.
    foreach ($this->structuredResultData->getCreatedContents() as $entity) {
      $entities = explode(':', $entity['entity_key']);
      $entity = $this->entityTypeManager->getStorage($entities[0])->load($entities[1]);
      if ($entity) {
        $entity->delete();
      }
    }
    // Reset all edited entities.
    foreach ($this->structuredResultData->getEditedContents() as $entity) {
      $entities = explode(':', $entity['entity_key']);
      $entity = $this->entityTypeManager->getStorage($entities[0])->load($entities[1]);
      $entityId = $this->createEntityId($entity);
      if (isset($this->originalEntities[$entityId])) {
        $this->originalEntities[$entityId]->save();
      }
    }
  }

  /**
   * Sets an original configuration for diffing.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $config
   *   The name of the configuration dependency.
   */
  public function setOriginalConfigurations(ConfigEntityInterface $config) {
    $configDependencyName = $config->getConfigDependencyName();
    // Only set once, otherwise its not the original.
    if (!isset($this->originalConfigurations[$configDependencyName])) {
      $originalConfigurations = $this->config->get($configDependencyName)->get();
      if ($originalConfigurations) {
        // Create a copy of the original configuration, to keep the state.
        $this->originalConfigurations[$configDependencyName] = clone $config;
        $this->originalConfigurationsArray[$configDependencyName] = $originalConfigurations;
      }
    }
  }

  /**
   * Set original content entities for reverting.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function setOriginalEntity(ContentEntityInterface $entity) {
    $entityId = $this->createEntityId($entity);
    if (!isset($this->originalEntities[$entityId])) {
      $this->originalEntities[$entityId] = clone $entity;
    }
  }

  /**
   * Get diff of configurations.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $config
   *   The name of the configuration dependency.
   *
   * @return array
   *   The diff.
   */
  public function getDiffOfConfigurations(ConfigEntityInterface $config) {
    $diff = [];
    $configDependencyName = $config->getConfigDependencyName();
    if (isset($this->originalConfigurations[$configDependencyName])) {
      $originalConfigurations = $this->originalConfigurationsArray[$configDependencyName];
      $currentConfigurations = $this->config->get($configDependencyName)->getRawData();
      $diff = DiffArray::diffAssocRecursive($currentConfigurations, $originalConfigurations);
      $minus_diff = DiffArray::diffAssocRecursive($originalConfigurations, $currentConfigurations);
    }
    return [
      'new' => $diff,
      'original' => $minus_diff ?? [],
    ];
  }

  /**
   * Get full context of the task.
   *
   * @param \Drupal\ai_agents\Task\TaskInterface $task
   *   The task.
   * @param bool $stripTags
   *   Strip tags.
   *
   * @return string
   *   The context.
   */
  public function getFullContextOfTask(TaskInterface $task, $stripTags = TRUE) {
    // Get the description and the comments.
    $context = "Task Title: " . $task->getTitle() . "\n";
    $context .= "Task Author: " . $task->getAuthorsUsername() . "\n";
    $context .= "Task Description:\n" . $task->getDescription();
    $context .= "\n--------------------------\n";
    if (count($task->getComments())) {
      $context .= "These are the following comments:\n";
      $context .= "--------------------------\n";
      $comments = $task->getComments();

      $i = 1;
      foreach ($comments as $comment) {
        $context .= "Comment order $i: \n";
        $context .= "Comment Author: " . $comment['role'] . "\n";
        $context .= "Comment:\n" . str_replace(["<br>", "<br />", "<br/>"], "\n", $comment['message']) . "\n\n";
        $i++;
      }
      $context .= "--------------------------\n";
    }
    return $stripTags ? strip_tags($context) : $context;
  }

  /**
   * Helper function to get description.
   *
   * @return string
   *   The description.
   */
  public function getDescription() {
    $capabilities = $this->agentsCapabilities();
    return $capabilities[key($capabilities)]['description'] ?? '';
  }

  /**
   * Get inputs as string.
   *
   * @return string
   *   The string.
   */
  public function getInputsAsString() {
    $capabilities = $this->agentsCapabilities();
    $outputString = "";
    foreach ($capabilities as $data) {
      foreach ($data['inputs'] as $input) {
        $outputString .= "Field name: $input[name] ($input[type]):\n";
        $outputString .= "Required:";
        $outputString .= isset($input['required']) && $input['required'] ? "Yes\n" : "No\n";
        $outputString .= "Description: $input[description]\n\n";
      }
    }
    return $outputString;
  }

  /**
   * Get outputs as string.
   *
   * @return string
   *   The string.
   */
  public function getOutputsAsString() {
    $capabilities = $this->agentsCapabilities();
    $outputString = "";
    foreach ($capabilities as $data) {
      foreach ($data['outputs'] as $title => $output) {
        $outputString .= "Field name: $title ($output[type]):\n";
        $outputString .= "Description: $output[description]\n";
      }
    }
    return $outputString;
  }

  /**
   * Helper function to run the whole process of one file.
   *
   * @param string $file
   *   The filename of the prompt.
   * @param array $userContext
   *   The user context.
   * @param string $module
   *   The module to fetch for.
   * @param string $subDirectory
   *   The subdirectory to look for the prompt in.
   * @param string $promptType
   *   The prompt type.
   * @param string $outputType
   *   The output type.
   *
   * @return array
   *   The response.
   */
  public function runSubAgent($file, array $userContext, $module, $subDirectory, $promptType = 'yaml', $outputType = 'json') {
    if ($promptType !== 'yaml') {
      throw new \Exception("The prompt type '$promptType' is not supported.");
    }
    if ($outputType !== 'json') {
      throw new \Exception("The output type '$outputType' is not supported.");
    }

    $prompt = '';
    switch ($promptType) {
      case 'yaml':
        $prompt = $this->actionYamlPrompts($file, $userContext, $module, $subDirectory);
        break;
    }

    $response = $this->runAiProvider($prompt['prompt'], [], TRUE, $file);

    switch ($outputType) {
      case 'json':
        $response = $this->promptJsonDecoder->decode($response);
        break;
    }
    return $response;
  }

  /**
   * Helper function to run the AI Provider.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $images
   *   The images.
   * @param bool $strip_tags
   *   If strip_tags HTML.
   * @param string $promptFile
   *   The prompt file.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The response.
   */
  public function runAiProvider($prompt, array $images = [], $strip_tags = TRUE, $promptFile = '') {
    $this->aiProvider->setChatSystemRole($prompt);
    $context = $this->getFullContextOfTask($this->task, $strip_tags);
    $message = new ChatMessage("user", $context, $images);
    $input = new ChatInput([
      $message,
    ]);
    $this->aiProvider->setConfiguration($this->aiConfiguration);
    $tags = [
      'ai_agents',
      'ai_agents_' . $this->getId(),
    ];
    if ($this->runnerId) {
      $tags[] = 'ai_agents_runner_' . $this->getRunnerId();
    }
    if ($promptFile) {
      $tags[] = 'ai_agents_prompt_' . explode('.', $promptFile)[0];
    }
    $response = $this->aiProvider->chat($input, $this->modelName, $tags);
    return $response->getNormalized();
  }

  /**
   * Builds action prompts.
   *
   * @param string $type
   *   The type of prompt to fetch.
   * @param array $userPrompts
   *   The user prompts to add to the action prompt.
   * @param string $module
   *   The module name to fetch prompt yamls from.
   * @param string $subDirectory
   *   The subdirectory to look for the prompt in.
   *
   * @return array|null
   *   The action prompt and the model to use.
   */
  public function actionYamlPrompts($type, array $userPrompts, $module, $subDirectory = '') {
    // Developers makes mistakes.
    $subDirectory = $subDirectory ? trim($subDirectory, '/') . '/' : '';
    $file = $this->extensionPathResolver->getPath('module', $module) . '/prompts/' . $subDirectory . basename($type, '.yml') . '.yml';
    if (!file_exists($file)) {
      throw new \Exception("The action prompt file '$file' does not exist.");
    }
    $data = Yaml::parse(file_get_contents($file));
    // Set introduction.
    $prompt = $data['prompt']['introduction'] . "\n\n";
    // Set formats to use.
    $invariable = count($data['prompt']['formats']) == 1 ? 'this format' : 'these formats';
    $structure = "";
    foreach ($data['prompt']['formats'] as $format) {
      $structure .= json_encode($format) . "\n";
    }
    $prompt .= "Do not include any explanations, only provide a RFC8259 compliant JSON response following $invariable without deviation:\n[$structure]\n";
    if (!empty($data['prompt']['possible_actions'])) {
      $prompt .= "This is the list of actions:\n";
      foreach ($data['prompt']['possible_actions'] as $action => $description) {
        $prompt .= "$action - $description\n";
      }
    }
    $prompt .= "\n";
    if (!empty($data['one_shot_learning_examples'])) {
      $prompt .= "Example response given\n";
      $prompt .= json_encode($data['prompt']['one_shot_learning_examples']) . "\n";
    }
    foreach ($userPrompts as $header => $userPrompt) {
      $prompt .= "\n\n-----------------------------------\n$header:\n$userPrompt\n-----------------------------------";
    }

    return [
      'prompt' => $prompt,
      'preferred_llm' => $data['preferred_llm'] ?? 'openai',
      'preferred_model' => $data['preferred_model'] ?? 'gpt-3.5-turbo',
    ];
  }

  /**
   * Helper function to create the runner id.
   *
   * @return string
   *   The runner id.
   */
  public function createRunnerId() {
    return $this->runnerId = $this->getId() . '_' . microtime();
  }

  /**
   * {@inheritDoc}
   */
  public function getRunnerId() {
    if (!$this->runnerId) {
      $this->createRunnerId();
    }
    return $this->runnerId;
  }

  /**
   * Helper function to set the runner id.
   *
   * @param string $runnerId
   *   The runner id.
   */
  public function setRunnerId($runnerId) {
    $this->runnerId = $runnerId;
  }

  /**
   * Set information.
   *
   * @param string $information
   *   The information.
   */
  public function setInformation($information) {
    $this->information = $information;
  }

  /**
   * Get information.
   *
   * @return string
   *   The information.
   */
  public function getInformation() {
    return $this->information;
  }

  /**
   * Load the configuration.
   */
  protected function loadConfiguration() {
    $settings = $this->config->get('ai_agents.settings')->get('agent_settings')[$this->getId()] ?? [];
    if (!empty($settings['plugin_settings'])) {
      $this->configuration = $settings['plugin_settings'];
    }
  }

  /**
   * Helper function to create unique id for content entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The unique id.
   */
  protected function createEntityId(ContentEntityInterface $entity): string {
    // Create a unique key from the entity type, id and language.
    $lang = $entity->language() ? $entity->language()->getId() : 'und';
    return $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $lang;
  }

}
