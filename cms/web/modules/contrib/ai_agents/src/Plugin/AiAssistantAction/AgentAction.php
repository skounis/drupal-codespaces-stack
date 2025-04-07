<?php

namespace Drupal\ai_agents\Plugin\AiAssistantAction;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Drupal\ai_assistant_api\Attribute\AiAssistantAction;
use Drupal\ai_assistant_api\Base\AiAssistantActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Agent action.
 */
#[AiAssistantAction(
  id: 'agent_action',
  label: new TranslatableMarkup('Agent Actions'),
)]
class AgentAction extends AiAssistantActionBase {

  use StringTranslationTrait;

  /**
   * The current running agent.
   *
   * @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The current running agent.
   */
  protected AiAgentInterface $currentAgent;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    protected PrivateTempStoreFactory $tmpStore,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AiAgentManager $agentManager,
    protected AiProviderPluginManager $providerPlugin,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $tmpStore);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }

    // Get all the agents.
    $agents = $this->agentManager->getDefinitions();
    $options = [];
    foreach ($agents as $agent_id => $agent_definition) {
      $instance = $this->agentManager->createInstance($agent_id);
      $options[$agent_id] = $instance->agentsCapabilities()[$agent_id]['name'];
    }
    $form['agent_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Agents to use'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['agent_ids'] ?? [],
      '#description' => $this->t('Select which agents to use for this plugin.'),
      '#states' => [
        'visible' => [
          ':input[name="action_plugin_agent_action[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'agent_ids' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function listActions(): array {
    $actions = [];

    if (empty($this->configuration['agent_ids'])) {
      return $actions;
    }

    foreach ($this->agentManager->getDefinitions() as $agent_id => $agent_definition) {
      if (is_array($this->configuration['agent_ids']) && !in_array($agent_id, $this->configuration['agent_ids'])) {
        continue;
      }
      $instance = $this->agentManager->createInstance($agent_id);
      // No access.
      if ($instance->hasAccess() != AccessResult::allowed()) {
        continue;
      }
      $actions[$agent_id]['label'] = $instance->agentsCapabilities()[$agent_id]['name'];
      $actions[$agent_id]['description'] = $instance->agentsCapabilities()[$agent_id]['description'];
      $actions[$agent_id]['plugin'] = 'agent_action';
      $actions[$agent_id]['id'] = $agent_id;
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function listContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function listUsageInstructions(): array {
    $instructions = [];

    if (empty($this->configuration['agent_ids'])) {
      return $instructions;
    }

    $config = $this->configFactory->get('ai_agents.settings');

    foreach ($this->agentManager->getDefinitions() as $agent_id => $agent_definition) {
      $instance = $this->agentManager->createInstance($agent_id);
      // No access.
      if ($instance->hasAccess() != AccessResult::allowed()) {
        continue;
      }
      // Not needed.
      if (!in_array($agent_id, array_values($this->configuration['agent_ids']))) {
        continue;
      }
      $extra_instructions = $config->get('agent_settings')[$agent_id]['usage_instructions'] ?? '';
      if ($extra_instructions) {
        $instructions[] = $extra_instructions;
      }
      elseif (isset($instance->agentsCapabilities()[$agent_id]['usage_instructions'])) {
        $instructions[] = $instance->agentsCapabilities()[$agent_id]['usage_instructions'];
      }
    }
    return $instructions;
  }

  /**
   * {@inheritdoc}
   */
  public function triggerAction(string $action_id, $parameters = []): void {
    // Make sure that the default Advanced JSON settings are set.
    $defaults = $this->providerPlugin->getDefaultProviderForOperationType('chat_with_complex_json');
    if (empty($defaults)) {
      $this->setOutputContext('ai_agent', $this->t('ERROR: Sorry, the Agents will not work unless you set up a default Chat with Complex JSON model. Please set one up on %link', [
        '%link' => Link::createFromRoute('AI Settings Form', 'ai.settings_form')->toString(),
      ]));
      return;
    }
    if (!in_array($action_id, $this->configuration['agent_ids'])) {
      $this->setOutputContext('ai_agent', 'ERROR: Sorry, the agent you are trying to use is not available.');
      return;
    }
    $this->currentAgent = $this->agentManager->createInstance($action_id);

    // If no access.
    if ($this->currentAgent->hasAccess() != AccessResult::allowed()) {
      $this->setOutputContext($this->currentAgent->getId(), 'ERROR: Sorry, you do not have access to this agent.');
      return;
    }
    // If the query is sent in incorrectly.
    if (is_array($parameters['query'])) {
      $query = "";
      foreach ($parameters['query'] as $key => $value) {
        $query .= $key . ": " . $value . "\n";
      }
      $parameters['query'] = $query;
    }
    // Get all tokens and replace them in the query.
    $task = new Task($parameters['query']);
    $task->setComments($this->messages ?? []);
    $this->currentAgent->setTask($task);
    $this->currentAgent->setAiProvider($this->providerPlugin->createInstance($defaults['provider_id']));
    $this->currentAgent->setModelName($defaults['model_id']);
    $this->currentAgent->setAiConfiguration([]);
    $this->currentAgent->setCreateDirectly(TRUE);
    // Set extra tags and who is running the agent.
    $tags = [];
    if (isset($parameters['thread_id'])) {
      $tags[] = 'ai_assistant_thread_' . $parameters['thread_id'];
    }
    if (isset($parameters['ai_assistant_api'])) {
      $tags[] = 'assistant_id__' . $parameters['ai_assistant_api'];
    }
    $tags[] = 'assistant_action_ai_agent';
    $this->currentAgent->setUserInterface('ai_assistants_api', $tags);
    $solvability = $this->currentAgent->determineSolvability();
    if ($solvability == AiAgentInterface::JOB_NEEDS_ANSWERS) {
      $questions = $this->currentAgent->askQuestion();
      if ($questions && is_array($questions)) {
        $this->setOutputContext($this->currentAgent->getId(), implode("\n", $questions));
      }
    }
    elseif ($solvability == AiAgentInterface::JOB_NOT_SOLVABLE) {
      $this->setOutputContext($this->currentAgent->getId(), 'ERROR: Sorry, I could not solve this, could you please rephrase?');
    }
    elseif ($solvability == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
      $this->setOutputContext($this->currentAgent->getId(), $this->currentAgent->answerQuestion());
    }
    elseif ($solvability == AiAgentInterface::JOB_INFORMS) {
      $this->setOutputContext($this->currentAgent->getId(), $this->currentAgent->inform());
    }
    else {
      $response = $this->currentAgent->solve();
      $this->setStructuredResults($this->currentAgent->getId(), $this->currentAgent->getStructuredOutput()->toArray());
      $this->setOutputContext($this->currentAgent->getId(), $response);
      if (!empty($parameters['creates_tokens'])) {
        $this->setOutputTokens($parameters['creates_tokens'], $response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function triggerRollback(): void {
    $this->currentAgent->rollback();
  }

  /**
   * {@inheritdoc}
   */
  public function provideFewShotLearningExample(): array {
    return [
      [
        'description' => 'Generate a car brands category type, filling it with items that the AI can make up and attaching it to a content type.',
        'schema' => [
          'actions' => [
            [
              'action' => 'taxonomy_agent',
              'plugin' => 'agent_action',
              'query' => 'Can you generate a vocabulary called Car Brands and come up with 10 Car Brands and fille the vocabulary Car Brands with those as items?',
            ],
            [
              'action' => 'field_type_agent',
              'plugin' => 'agent_action',
              'query' => 'Can you create a taxonomy field called Car Brands that is referencing the vocabulary Car Brands?',
            ],
          ],
        ],
      ],
    ];
  }

}
