<?php

namespace Drupal\ai_agents_explorer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines an AI Agent Explorer Controller.
 */
class AiAgentExplorerController extends ControllerBase {


  /**
   * The AI Provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The AI Agents Plugin Manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected $agentsManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $ai_manager, AiAgentManager $agents_manager, Request $request, EntityTypeManagerInterface $entity_type_manager) {
    $this->providerManager = $ai_manager;
    $this->agentsManager = $agents_manager;
    $this->currentRequest = $request;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('ai.provider'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
    );
    return $instance;
  }

  /**
   * Poll an agent.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function pollAgent($uuid) {
    // Get the logs.
    $storage = $this->entityTypeManager->getStorage('ai_agent_decision');
    $decisions = $storage->loadByProperties(['runner_id' => $uuid]);
    $entries = [];
    foreach ($decisions as $decision) {
      // Check if its a json response.
      $test = json_decode($decision->response_given->value);
      if (is_array($test)) {
        $answer = json_encode(json_decode($decision->response_given->value), JSON_PRETTY_PRINT);
      }
      else {
        $answer = htmlentities($decision->response_given->value);
      }
      $entries[] = [
        'id' => $decision->id(),
        'label' => $decision->label->value,
        'created' => $decision->microtime->value,
        'json' => $answer,
      ];
    }

    return new JsonResponse([
      'success' => $uuid,
      'time' => microtime(TRUE),
      'entries' => $entries,
    ]);
  }

  /**
   * Run an agent.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function runAgent() {
    // Get the prompt from the request.
    $prompt = $this->currentRequest->get('prompt');
    $agent_name = $this->currentRequest->get('agent');
    $provider_name = $this->currentRequest->get('model');
    $images = $this->currentRequest->get('images');
    $runner_id = $this->currentRequest->get('runner_id');

    try {
      $provider = $this->providerManager->loadProviderFromSimpleOption($provider_name);
      $agent = $this->agentsManager->createInstance($agent_name);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
    $model_name = $this->providerManager->getModelNameFromSimpleOption($provider_name);

    // Store the agent and prompt in a session, Drupal style.
    $this->currentRequest->getSession()->set('ai_agent_explorer', [
      'agent' => $agent_name,
      'prompt' => $prompt,
    ]);
    // The actual task, will support files later.
    $task = new Task($prompt);
    if (!empty($images)) {
      $task_files = [];
      foreach ($images as $fid) {
        $file = $this->entityTypeManager->get('file')->load($fid);
        $task_files[] = $file;
      }
      $task->setFiles($task_files);
    }
    // Set the runner id.
    $agent->setRunnerId($runner_id);
    $agent->setTask($task);
    // Settings up AI.
    $agent->setAiProvider($provider);
    $agent->setModelName($model_name);
    $agent->setAiConfiguration([]);
    // Create, not suggest.
    $agent->setCreateDirectly(TRUE);
    // Check if it can solve it.
    $response = '';
    try {
      $can_solve = $agent->determineSolvability();
      if ($can_solve == AiAgentInterface::JOB_SOLVABLE) {
        // Solve it.
        $response = 'Status: Solve, Response: ' . $agent->solve();
      }
      elseif ($can_solve == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
        $response = 'Status: Answer Question, Response: ' . $agent->answerQuestion();
      }
      elseif ($can_solve == AiAgentInterface::JOB_NEEDS_ANSWERS) {
        $response = 'Status: Needs Answers, Response: ' . implode("\n", $agent->askQuestion());
      }
      elseif ($can_solve == AiAgentInterface::JOB_INFORMS) {
        $response = 'Status: Informs, Response: ' . $agent->inform();
      }
      elseif ($can_solve == AiAgentInterface::JOB_NOT_SOLVABLE) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Status: Not Solvable',
          'time' => microtime(TRUE),
        ]);
      }
      else {
      }
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $e->getMessage(),
        'time' => microtime(TRUE),
      ], 500);
    }
    return new JsonResponse([
      'success' => TRUE,
      'message' => $response,
      'time' => microtime(TRUE),
    ]);
  }

}
