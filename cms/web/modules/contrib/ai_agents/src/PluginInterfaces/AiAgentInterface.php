<?php

namespace Drupal\ai_agents\PluginInterfaces;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai_agents\Output\StructuredResultDataInterface;
use Drupal\ai_agents\Task\TaskInterface;

/**
 * Interface for AI Agents modifiers.
 */
interface AiAgentInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * The status of the job.
   */
  // Not solvable.
  const JOB_NOT_SOLVABLE = 0;
  // Solvable.
  const JOB_SOLVABLE = 1;
  // Needs answers.
  const JOB_NEEDS_ANSWERS = 2;
  // Should answer question.
  const JOB_SHOULD_ANSWER_QUESTION = 3;
  // Job informs.
  const JOB_INFORMS = 4;

  /**
   * Gets the plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getId();

  /**
   * Get the module name.
   *
   * @return string
   *   The module name.
   */
  public function getModuleName();

  /**
   * Gets the AI Provider.
   *
   * @return \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   *   The AI Provider.
   */
  public function getAiProvider();

  /**
   * Sets the AI Provider.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $aiProvider
   *   The AI Provider.
   */
  public function setAiProvider($aiProvider);

  /**
   * Gets the model name.
   *
   * @return string
   *   The model name.
   */
  public function getModelName();

  /**
   * Sets the model name.
   *
   * @param string $modelName
   *   The model name.
   */
  public function setModelName($modelName);

  /**
   * Gets the ai provider configuration.
   *
   * @return array
   *   The ai provider configuration.
   */
  public function getAiConfiguration();

  /**
   * Sets the ai provider configuration.
   *
   * @param array $aiConfiguration
   *   The ai provider configuration.
   */
  public function setAiConfiguration(array $aiConfiguration);

  /**
   * Gets the task.
   *
   * @return \Drupal\ai_agents\Task\TaskInterface
   *   The task.
   */
  public function getTask();

  /**
   * Sets the task.
   *
   * @param \Drupal\ai_agents\Task\TaskInterface $task
   *   The task.
   */
  public function setTask(TaskInterface $task);

  /**
   * Get the mode of the agent.
   *
   * @return bool
   *   The mode of the agent.
   */
  public function getCreateDirectly();

  /**
   * Set the mode of the agent.
   *
   * @param bool $createDirectly
   *   The mode of the agent.
   */
  public function setCreateDirectly($createDirectly);

  /**
   * Get the list of the available agents names.
   *
   * @return array
   *   The list of the agents names.
   */
  public function agentsNames();

  /**
   * Get the list of the agents name with a description of capabilities.
   *
   * @return array
   *   The agents with a description of capabilities and outputs.
   */
  public function agentsCapabilities();

  /**
   * Checks if the agent is available.
   *
   * @return bool
   *   TRUE if the agent is available, FALSE otherwise.
   */
  public function isAvailable();

  /**
   * Get the runner id.
   *
   * @return string
   *   The runner id.
   */
  public function getRunnerId();

  /**
   * Get the extra tags.
   *
   * @return array
   *   The extra tags.
   */
  public function getExtraTags();

  /**
   * Answers a question from the PM AI or end-users directed at an agent.
   */
  public function answerQuestion();

  /**
   * Information about what happened.
   */
  public function inform();

  /**
   * Determine if the agent can solve the instructions.
   *
   * @return int
   *   One of the JOB_* constants.
   */
  public function determineSolvability();

  /**
   * Determine if the user can use the agent. Returns an AccessResult.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function hasAccess();

  /**
   * Ask a question to the PM AI or end-users to solve the issue.
   *
   * @return array
   *   The question to be asked to the PM AI or end-users.
   */
  public function askQuestion();

  /**
   * Take instructions from the PM AI and solve it.
   *
   * @return string
   *   The solution from the agent.
   */
  public function solve();

  /**
   * Approve solution if it was blueprinted.
   *
   * @return void
   *   The solution from the agent.
   */
  public function approveSolution();

  /**
   * Get the structured output of the agent.
   *
   * @return \Drupal\ai_agents\Output\StructuredResultDataInterface
   *   The structured output.
   */
  public function getStructuredOutput(): StructuredResultDataInterface;

  /**
   * Provide optionally who is the user interface that started the agent.
   *
   * @param string $userInterface
   *   The user interface that started the agent.
   * @param array $extraTags
   *   Extra tags to be added to the agents AI prompts.
   */
  public function setUserInterface($userInterface, array $extraTags = []);

  /**
   * Rollback changes.
   *
   * This makes it possible to rollback configuration changes that has happened
   * during the agent's run.
   */
  public function rollback();

}
