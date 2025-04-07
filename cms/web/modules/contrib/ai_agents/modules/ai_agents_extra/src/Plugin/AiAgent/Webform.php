<?php

namespace Drupal\ai_agents_extra\Plugin\AiAgent;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\PluginBase\AiAgentBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Service\AgentHelper;
use Drupal\ai_agents_extra\Service\WebformAgent\WebformActions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Webform agent.
 */
#[AiAgent(
  id: 'webform_agent',
  label: new TranslatableMarkup('Webform Agent'),
)]
class Webform extends AiAgentBase implements ContainerFactoryPluginInterface {

  /**
   * Questions to ask.
   *
   * @var array
   */
  protected $questions;

  /**
   * Task type.
   *
   * @var string
   */
  protected $taskType;

  /**
   * The YAML to keep in context.
   *
   * @var array
   *   The base yaml.
   */
  protected array $baseYaml;

  /**
   * The example yaml.
   */
  protected array $exampleYaml;

  /**
   * The webform.
   *
   * @var \Drupal\webform\Entity\Webform
   */
  protected $webForm;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AgentHelper $agentHelper,
    FileSystemInterface $fileSystem,
    ConfigFactoryInterface $configFactory,
    AccountInterface $currentUser,
    ExtensionPathResolver $extensionPathResolver,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiProviderPluginManager $aiProvider,
    EntityTypeManagerInterface $entityTypeManager,
    protected WebformActions $webformActions,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $agentHelper, $fileSystem, $configFactory, $currentUser, $extensionPathResolver, $promptJsonDecoder, $aiProvider, $entityTypeManager);
    $this->baseYaml = Yaml::parse(file_get_contents($extensionPathResolver->getPath('module', 'ai_agents') . '/resources/webform_example.yml'));
    $this->exampleYaml = Yaml::parse(file_get_contents($extensionPathResolver->getPath('module', 'ai_agents') . '/resources/webform_example.yml'));
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
      $container->get('ai_agents_extra.webform_actions')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return 'webform_agent';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      'Webform Agent',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getData() {
    return $this->webformActions->baseYaml;
  }

  /**
   * {@inheritDoc}
   */
  public function setData($data) {
    $this->baseYaml = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function setFixedData($data) {
    $this->baseYaml = $this->exampleYaml;
    $this->baseYaml['id'] = $data['webform_id'];
    $this->baseYaml['title'] = $data['webform_title'];
    $this->baseYaml['status'] = $data['webform_open'];
    $this->baseYaml['description'] = $data['webform_description'];
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    $description = "This agent is capable of creating and editing ";
    $description .= "Drupal webforms, which can be used as questionnaires, ";
    $description .= "surveys or other type of forms. This does explicitly not ";
    $description .= "delete webforms.";
    return [
      'webform_agent' => [
        'name' => 'Webform Agent',
        'description' => $description,
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'The prompt to create, edit or ask questions about a webform.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about the webform.',
            'type' => 'string',
          ],
          'link_url' => [
            'description' => 'The link to the webform edited or created.',
            'type' => 'url',
          ],
          'edit_url' => [
            'description' => 'The link to edit the webform edited or created.',
            'type' => 'url',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isAvailable() {
    // Check if webform module is installed.
    return $this->agentHelper->isModuleEnabled('webform');
  }

  /**
   * {@inheritDoc}
   */
  public function getRetries() {
    return 5;
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
  }

  /**
   * {@inheritDoc}
   */
  public function answerQuestion() {
    return "string";
  }

  /**
   * {@inheritDoc}
   */
  public function getHelp() {
    return "string";
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess() {
    // Check for permissions.
    if (!$this->currentUser->hasPermission('administer webform')) {
      return AccessResult::forbidden();
    }
    return parent::hasAccess();
  }

  /**
   * {@inheritDoc}
   */
  public function determineSolvability() {
    parent::determineSolvability();
    $this->taskType = $this->determineTypeOfTask();
    switch ($this->taskType) {
      case 'create':
        $this->determineCreate();
        // If questions are empty, we can solve the issue.
        if (empty($this->questions)) {
          return AiAgentInterface::JOB_SOLVABLE;
        }
        // Otherwise we need answers.
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'edit':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'question':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'submission_info':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'submission_delete':
        return AiAgentInterface::JOB_SOLVABLE;

    }

    return "string";
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
    // If its creation.
    switch ($this->taskType) {
      case 'create':
        return $this->createWebform();

      case 'edit':
        return $this->editWebform();

      case 'question':
        return $this->answerQuestion();

      default:
        return $this->createContextWebform();
    }
  }

  /**
   * Create a webform.
   *
   * @param string $mode
   *   The mode if its created or recreated.
   *
   * @return string
   *   The solution.
   */
  public function createWebform($mode = 'created') {
    $data = $this->agentHelper->runSubAgent('getFirstSetup', [
      'Prompt (A PM ticket and comments)' => $this->getFullContextOfTask($this->task),
    ]);

    if (!isset($data[0]) || (isset($data[0]['type']) && $data[0]['type'] === 'no_information')) {
      throw new \Exception($data[0]['value'] ?? 'No information about a webform.');
    }
    $this->webformActions->setup($this->baseYaml, $this);
    $webForm = $this->webformActions->generateFirstWebForm($data);
    $message = $this->t("The webform has been %mode. You can find it at %rendered_link. The id if you refer to it is %id<br /><br />", [
      '%rendered_link' => $webForm->toLink()->toString(),
      '%id' => $webForm->id(),
      '%mode' => $mode,
    ]);
    $message .= $this->t("You can edit the webform at %edit_link<br />", [
      '%edit_link' => $webForm->toLink('Edit')->toString(),
    ]);
    $message .= $this->t("You can find the results at %results_link<br /><br />", [
      '%results_link' => $webForm->toLink('Results')->toString(),
    ]);
    $message .= $this->t("Please review it and if you have changes, write them in the comments and assign it back to me and set it todo. <br />");
    $message .= $this->t("If the result is completely off you can also comment that I should redo the whole thing. <br />");
    $message .= $this->t("Note that the form is unpublished by default and that you have to tell us to publish it. Thanks!");
    return $message;
  }

  /**
   * Create contextualized webform.
   *
   * @return string
   *   The webform.
   */
  public function createContextWebform($mode = 'created') {
    $webForm = $this->webformActions->generateContextualizedWebForm($this->baseYaml);
    $message = $this->t("The webform has been %mode. You can find it at %rendered_link. The id if you refer to it is %id<br /><br />", [
      '%rendered_link' => $webForm->toLink()->toString(),
      '%id' => $webForm->id(),
      '%mode' => $mode,
    ]);
    $message .= $this->t("You can edit the webform at %edit_link<br />", [
      '%edit_link' => $webForm->toLink('Edit')->toString(),
    ]);
    $message .= $this->t("You can find the results at %results_link<br /><br />", [
      '%results_link' => $webForm->toLink('Results')->toString(),
    ]);
    $message .= $this->t("Please review it and if you have changes, write them in the comments and assign it back to me and set it todo. <br />");
    $message .= $this->t("If the result is completely off you can also comment that I should redo the whole thing. <br />");
    $message .= $this->t("Note that the form is unpublished by default and that you have to tell us to publish it. Thanks!");
    return $message;
  }

  /**
   * Edit the webform.
   *
   * @return string
   *   The solution.
   */
  public function editWebform() {
    $this->baseYaml = $this->webForm->toArray();
    $this->webformActions->setup($this->baseYaml, $this);
    $result = $this->agentHelper->runSubAgent('formActions', []);

    if (empty($result)) {
      throw new \Exception('No information about a webform.');
    }
    $response = "";
    foreach ($result as $action) {
      $method = 'formAction' . ucfirst($action['action']);
      if (method_exists($this->webformActions, $method)) {
        $response .= $this->webformActions->{$method}($action);
      }
    }
    $this->webformActions->save();
    return $response;
  }

  /**
   * Redo a webform.
   *
   * @return string
   *   The solution.
   */
  public function redoWebform() {
    $this->deleteWebform();
    return $this->createWebform('re-done');
  }

  /**
   * Delete a webform.
   *
   * @return string
   *   The solution.
   */
  public function deleteWebform() {
    if (!empty($this->webForm)) {
      $label = $this->webForm->label();
      $this->webForm->delete();
      $this->webForm = NULL;
      return $this->t('The webform %label has been deleted.', [
        '%label' => $label,
      ]);
    }
    else {
      return 'No webform to delete.';
    }
  }

  /**
   * Determine if the context is asking for creation, editing or a question.
   *
   * @return string
   *   The context.
   */
  public function determineTypeOfTask() {
    $data = $this->agentHelper->runSubAgent('determineTask', [
      'Current Webforms available' => $this->getWebforms(),
    ]);

    if (isset($data[0]['action']) && in_array($data[0]['action'], [
      'create',
      'edit',
      'question',
      'submission_info',
      'submission_delete',
    ])) {
      if (isset($data[0]['webform_id'])) {
        $webform = $this->entityTypeManager->getStorage('webform')->load($data[0]['webform_id']);
        if ($webform) {
          $this->webForm = $webform;
        }
      }

      return $data[0]['action'];
    }
    throw new \Exception('Invalid action in Webform Determining task.');
  }

  /**
   * Determine that all information to create a webform is available.
   */
  public function determineCreate() {
    $data = $this->agentHelper->runSubAgent('determineCreate', []);

    if (isset($data[0]['action']) && $data[0]['action'] !== 'no_questions') {
      $questions = [];
      foreach ($data as $question) {
        $questions[] = $question['question'];
      }
      $this->questions = $questions;
    }
  }

  /**
   * Get all webforms as text list.
   *
   * @return string
   *   The webforms.
   */
  public function getWebforms() {
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();
    $webformList = [];
    foreach ($webforms as $webform) {
      $webformList[] = $webform->label() . ' (id: ' . $webform->id() . ')';
    }
    return implode(', ', $webformList);
  }

}
