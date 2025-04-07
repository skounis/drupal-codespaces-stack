<?php

namespace Drupal\ai_agents\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_agents\Exception\AgentRetryableValidationException;
use Drupal\ai_agents\Exception\AgentValidationException;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentValidationPluginManager;
use Drupal\ai_agents\Task\TaskInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The agent helper.
 */
class AgentHelper {

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   */
  protected ProviderProxy $aiProvider;

  /**
   * The AI agent.
   *
   * @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   */
  protected AiAgentInterface $agent;

  /**
   * The model name.
   *
   * @var string
   */
  protected string $modelName;

  /**
   * The AI provider configuration.
   *
   * @var array
   */
  protected array $aiConfiguration;

  /**
   * The task.
   *
   * @var \Drupal\ai_agents\Task\TaskInterface
   */
  protected TaskInterface $task;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * The runner id.
   *
   * @var string
   */
  protected string $runnerId;

  /**
   * Extra tags.
   *
   * @var array
   */
  protected array $extraTags;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module handler.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt json decoder.
   * @param \Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface $promptCodeBlockExtractor
   *   The prompt code block extractor.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory to load overrides.
   * @param \Drupal\ai_agents\PluginManager\AiAgentValidationPluginManager $validationPluginManager
   *   The validation plugin manager.
   */
  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
    protected ModuleHandler $moduleHandler,
    protected PromptJsonDecoderInterface $promptJsonDecoder,
    protected PromptCodeBlockExtractorInterface $promptCodeBlockExtractor,
    protected ConfigFactoryInterface $configFactory,
    protected AiAgentValidationPluginManager $validationPluginManager,
  ) {
  }

  /**
   * Check if module is enabled.
   *
   * @param string $name
   *   The modules name.
   *
   * @return bool
   *   If the module is enabled or not.
   */
  public function isModuleEnabled(string $name): bool {
    return $this->moduleHandler->moduleExists($name);
  }

  /**
   * Setup all the parts of the agent helper.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent.
   */
  public function setupRunner(AiAgentInterface $agent): void {
    $this->agent = $agent;
    $this->task = $agent->getTask();
    $this->aiProvider = $agent->getAiProvider();
    $this->modelName = $agent->getModelName();
    $this->aiConfiguration = $agent->getAiConfiguration();
    $this->pluginId = $agent->getId();
    $this->runnerId = $agent->getRunnerId();
    $this->extraTags = $agent->getExtraTags();
  }

  /**
   * Helper function to run the whole process of one file.
   *
   * @param string $file
   *   The filename of the prompt.
   * @param array $userContext
   *   The user context.
   * @param string $questionContext
   *   If the question from the user should change.
   * @param string $promptType
   *   The prompt type.
   * @param string $outputType
   *   The output type.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|array|string
   *   The response.
   *
   * @throws \Exception
   */
  public function runSubAgent(string $file, array $userContext, string $questionContext = '', string $promptType = 'yaml', string $outputType = 'json'): ChatMessage|array|string {
    if (empty($this->task) || empty($this->aiProvider) || empty($this->modelName) || !isset($this->aiConfiguration) || empty($this->pluginId)) {
      throw new \Exception("The agent helper has not been setup properly.");
    }
    if ($promptType !== 'yaml') {
      throw new \Exception("The prompt type '$promptType' is not supported.");
    }
    if ($outputType !== 'json') {
      throw new \Exception("The output type '$outputType' is not supported.");
    }

    $prompt = '';
    switch ($promptType) {
      case 'yaml':
        $prompt = $this->actionYamlPrompts($file, $userContext);
        break;
    }

    // Check if the task has images.
    $images = [];
    if (!empty($this->task->getFiles())) {
      foreach ($this->task->getFiles() as $fileEntity) {
        $image = new ImageFile(file_get_contents($fileEntity->getFileUri()), $fileEntity->getMimeType(), $fileEntity->getFilename());
        $images[] = $image;
      }
    }
    $response = $this->runAiProvider($prompt['prompt'], $questionContext, $images, TRUE, $file);
    $outputType = $prompt['output_format'] ?? $outputType;

    $success = FALSE;

    foreach ($prompt['validation'] as $validation) {
      try {

        // If the validation doesn't throw an exception on error, this will
        // catch any bool responses, or get set to TRUE by a success. We
        // cannot retry on failure here as we have no information to pass to
        // the LLM about why it failed.
        $success = $this->validationPluginManager->validate($validation, $response);
      }
      catch (AgentRetryableValidationException $e) {
        $retries = 0;
        $failPrompt = $e->getPrompt();

        while ($retries < $prompt['retries'] && $success === FALSE) {
          try {
            $prompt = $this->actionYamlPrompts($file, $userContext, $failPrompt);
            $response = $this->runAiProvider($prompt['prompt'], $questionContext, [], TRUE, $file);
            $success = $this->validationPluginManager->validate($validation, $response);
          }
          catch (AgentRetryableValidationException $e) {

            // We may have an updated error prompt, so we'll replace the one
            // we have.
            $failPrompt = $e->getPrompt();
            $retries++;
          }
          catch (\Exception $e) {

            // We have no further information we can pass to the LLM, so
            // we'll just try again.
            $retries++;
          }
        }
      }
      catch (\Exception $e) {
        // Nothing to do here as we have no additional information to pass
        // to the LLM about why the response failed validation.
      }

      if (!$success) {
        throw new AgentValidationException('LLM response failed validation.');
      }
    }

    switch ($outputType) {
      case 'json':
        $response = $this->promptJsonDecoder->decode($response);
        break;

      case 'text':
        $response = $response->getText();
        break;

      default:
        $response = $this->promptCodeBlockExtractor->extract($response, $outputType);
        break;
    }

    return $response;
  }

  /**
   * Helper function to run the AI Provider.
   *
   * @param string $prompt
   *   The prompt.
   * @param string $context
   *   The context.
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
  public function runAiProvider(string $prompt, string $context = '', array $images = [], bool $strip_tags = TRUE, string $promptFile = ''): ChatMessage {
    $this->aiProvider->setChatSystemRole($prompt);
    $context = $context != '' ? $context : $this->getFullContextOfTask($this->task, $strip_tags);
    $message = new ChatMessage("user", $context, $images);
    $input = new ChatInput([
      $message,
    ]);
    $this->aiProvider->setConfiguration($this->aiConfiguration);
    $tags = [
      'ai_agents',
      'ai_agents_' . $this->pluginId,
    ];
    if ($this->runnerId) {
      $tags[] = 'ai_agents_runner_' . $this->runnerId;
    }
    if ($promptFile) {
      $tags[] = 'ai_agents_prompt_' . explode('.', $promptFile)[0];
    }
    if (!empty($this->extraTags)) {
      $tags = array_merge($tags, $this->extraTags);
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
   * @param string $failPrompt
   *   A string to add to the prompts on failure.
   *
   * @return array|null
   *   The action prompt and the model to use.
   *
   * @throws \Exception
   */
  public function actionYamlPrompts(string $type, array $userPrompts, string $failPrompt = ''): ?array {
    // Developers makes mistakes.
    $subDirectory = $this->agent->getId() . '/';
    $file = $this->extensionPathResolver->getPath('module', $this->agent->getModuleName()) . '/prompts/' . $subDirectory . basename($type, '.yml') . '.yml';
    if (!file_exists($file)) {
      throw new \Exception("The action prompt file '$file' does not exist.");
    }
    $data = $this->loadYaml($file);

    // Set introduction.
    $prompt = $data['prompt']['introduction'] . "\n\n";
    // Set the fail prompt.
    if ($failPrompt) {
      $prompt .= $failPrompt . "\n\n";
    }
    // Set potential extra instructions.
    $prompt .= $this->getExtraInstructions($file);
    // Set formats to use.
    $invariable = isset($data['prompt']['formats']) && count($data['prompt']['formats']) == 1 ? 'this format' : 'these formats';
    $structure = "";
    if (!empty($data['prompt']['formats'])) {
      foreach ($data['prompt']['formats'] as $format) {
        $structure .= json_encode($format) . "\n";
      }
    }
    if ($structure) {
      if (empty($data['output_format']) || $data['output_format'] == 'json') {
        $prompt .= "Do not include any explanations, only provide a RFC8259 compliant JSON response following $invariable without deviation:\n[$structure]\n";
      }
      elseif ($data['output_format'] == 'yaml') {
        $prompt .= "Do not include any explanations, only provide a YAML response following $invariable without deviation:\n$structure";
      }
    }
    if (!empty($data['prompt']['possible_actions'])) {
      $prompt .= "This is the list of actions:\n";
      foreach ($data['prompt']['possible_actions'] as $action => $description) {
        $prompt .= "$action - $description\n";
      }
    }
    $prompt .= "\n";
    if (!empty($data['prompt']['one_shot_learning_examples'])) {
      $prompt .= "Example response(s) given:\n";
      if (empty($data['output_format']) || $data['output_format'] == 'json') {
        $prompt .= "```json\n" . json_encode($data['prompt']['one_shot_learning_examples']) . "```\n";
      }
      elseif ($data['output_format'] == 'yaml') {
        foreach ($data['prompt']['one_shot_learning_examples'] as $example) {
          $prompt .= "```yaml\n" . Yaml::dump($example, 10, 2) . "```\n\n";
        }
      }
    }
    foreach ($userPrompts as $header => $userPrompt) {
      $prompt .= "\n\n-----------------------------------\n$header:\n$userPrompt\n-----------------------------------";
    }

    return [
      'prompt' => $prompt,
      'preferred_llm' => $data['preferred_llm'] ?? 'openai',
      'preferred_model' => $data['preferred_model'] ?? 'gpt-3.5-turbo',
      'validation' => $data['validation'] ?? [],
      'retries' => $data['retries'] ?? 0,
      'output_format' => $data['output_format'] ?? 'json',
    ];
  }

  /**
   * Load the YAML from file or configuration.
   *
   * @param string $file
   *   The file.
   *
   * @return array
   *   The YAML.
   */
  public function loadYaml(string $file): array {
    $config = $this->configFactory->get('ai_agents.settings')->get($this->agent->getId());
    $id = str_replace('.', '', basename($file));

    $data = Yaml::parse(file_get_contents($file));

    if (isset($config['yaml_override'][$id])) {
      $data['prompt'] = Yaml::parse($config['yaml_override'][$id]);
    }
    // @todo add caching.
    return $data;
  }

  /**
   * Get extra instructions.
   *
   * @param string $file
   *   The file.
   *
   * @return string
   *   The extra instructions.
   */
  public function getExtraInstructions(string $file): string {
    $config = $this->configFactory->get('ai_agents.settings')->get($this->agent->getId());
    $id = str_replace('.', '', basename($file));
    return isset($config['extra_instructions'][$id]) ? $config['extra_instructions'][$id] . "\n\n" : '';
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
  public function getFullContextOfTask(TaskInterface $task, bool $stripTags = TRUE): string {
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

}
