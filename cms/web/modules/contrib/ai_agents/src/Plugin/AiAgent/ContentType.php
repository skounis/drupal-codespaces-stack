<?php

namespace Drupal\ai_agents\Plugin\AiAgent;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\Exception\AgentProcessingException;
use Drupal\ai_agents\PluginBase\AiAgentBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\node\Entity\NodeType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Node Content Type Agent.
 */
#[AiAgent(
  id: 'node_content_type_agent',
  label: new TranslatableMarkup('Node Content Type Agent'),
)]
class ContentType extends AiAgentBase {

  use DependencySerializationTrait;

  /**
   * Questions to ask.
   *
   * @var array
   */
  protected $questions = [];

  /**
   * The full result of the task.
   *
   * @var array
   */
  protected $result;

  /**
   * The full data of the initial task.
   *
   * @var array
   */
  protected $data;

  /**
   * Task type.
   *
   * @var string
   */
  protected $taskType;

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
    $parent_instance->entityFieldManager = $container->get('entity_field.manager');
    return $parent_instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return 'node_content_type_agent';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      'Node/Content-Type Agent',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [
      'node_content_type_agent' => [
        'name' => 'Node Content Type Agent',
        'description' => "This is capable of adding, editing, informing or removing basic information on a Drupal node type/content type and also answer questions about node types. This is your main agent to generate new content types. This does not add path or field information. Note: This agent can not create or edit nodes, as in content. Also note that it doesn't create a body field, you need to add that yourself or via the field agent if that is what you want to do. It also can not work with the workflow module, just change settings on the node type.",
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'The prompt to create, edit, delete or ask questions about node types or content types.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about the node type or content type or the content type generated.',
            'type' => 'string',
          ],
        ],
      ],
    ];
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
  public function isAvailable() {
    // Check if node module is installed.
    return $this->agentHelper->isModuleEnabled('node');
  }

  /**
   * {@inheritDoc}
   */
  public function isNotAvailableMessage() {
    return $this->t('You need to enable the node module to do this.');
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
  public function answerQuestion() {
    $data = $this->agentHelper->runSubAgent('answerQuestion', [
      'The list of node types' => $this->getVerboseNodeTypesAsString(),
    ]);

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
  public function hasAccess() {
    // Check for permissions.
    if (!$this->currentUser->hasPermission('administer content types')) {
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
      case 'blueprint':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'edit':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'delete':
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'information':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'fail':
        return AiAgentInterface::JOB_INFORMS;
    }

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
    switch ($this->data[0]['action']) {
      case 'create':
        $this->createNodeType();
        break;

      case 'blueprint':
        return $this->data[0];

      case 'edit':
        $this->editNodeType();
        break;

      case 'delete':
        $this->result[] = "The agent does not have permissions to delete node types, but you can ask me for instructions to do it yourself.";
        break;

      default:
        $this->result[] = 'We could not figure out what you wanted to do.';
    }
    return implode("\n", $this->result);
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
    $this->data[0]['action'] = 'create';
  }

  /**
   * Check so all requirements are there.
   *
   * @return bool
   *   If all requirements are there.
   */
  public function checkRequirements() {
    return TRUE;
  }

  /**
   * Determine if the context is asking a question or wants a audit done.
   *
   * @return string
   *   The context.
   */
  public function determineTypeOfTask() {
    $data = $this->agentHelper->runSubAgent('determineNodeTypeTask', [
      'All currently available node types' => $this->getNodeTypesAsString(),
    ]);

    if (isset($data[0]['action']) && in_array($data[0]['action'], [
      'create',
      'edit',
      'delete',
      'information',
      'fail',
    ])) {
      // If its edit or delete, we need to know if the node type exists.
      if (in_array($data[0]['action'], ['edit', 'delete'])) {
        if ($data[0]['action'] == 'delete') {
          $this->questions[] = "You do not have permission to use the delete function, if you feel you need it to answer the user's request, please inform the user that you don't have permission and that the user can ask for help doing it themselves or obtaining permission from an admin.";
        }
        if (!$this->doesNodeTypeExist($data[0]['data_name'])) {
          // Check if we can find a similar node type.
          $similarNodeType = $this->findSimilarNodeType();
          // Fail completely.
          if (empty($similarNodeType)) {
            throw new \Exception('We could not figure out which node type you meant. Please specify better.');
          }
          // If there is a similar, we ask.
          $this->information = 'We could not find the node type you are looking for. Do you mean ' . $similarNodeType['readable_name'] . ' (' . $similarNodeType['data_name'] . ')?';
          $data[0]['action'] = 'fail';
        }
      }
      // Set blueprint.
      if ($data[0]['action'] === 'create' && !$this->createDirectly) {
        $data[0]['action'] = 'blueprint';
      }
      // Inform the user of failure.
      if ($data[0]['action'] === 'fail') {
        $this->information = $data[0]['fail_message'];
      }

      $this->data = $data;
      return $data[0]['action'];
    }
    throw new \Exception('The content type agent can not understand your request.');
  }

  /**
   * Try to get a similar node type.
   *
   * @return string|array
   *   The node type.
   */
  public function findSimilarNodeType() {
    $nodeTypes = $this->getNodeTypes();
    $list = "";
    foreach ($nodeTypes as $dataName => $nodeType) {
      $list .= $nodeType . ' - dataname: ' . $dataName . "\n";
    }
    $data = $this->agentHelper->runSubAgent('determineNodeType', [
      'Node Types List' => $list,
    ]);
    return !empty($data[0]['node_type']) ? [
      'data_name' => $data[0]['node_type'],
      'readable_name' => $nodeTypes[$data[0]['node_type']],
    ] : '';
  }

  /**
   * Does the node type exist.
   *
   * @param string $dataName
   *   The data name.
   *
   * @return bool
   *   If the node type exists.
   */
  public function doesNodeTypeExist($dataName) {
    $nodeTypes = $this->getNodeTypes();
    return in_array($dataName, array_keys($nodeTypes));
  }

  /**
   * Delete a node type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The node type.
   */
  public function deleteNodeType() {
    $type = NodeType::load($this->data[0]['data_name']);
    if (!$type) {
      throw new \Exception('We could not figure out which node type you meant. Please specify better or check the list %here.');
    }

    $type->delete();
    return $type;
  }

  /**
   * Edit a node type.
   */
  public function editNodeType() {
    $types = $this->getNodeTypes();
    if (!isset($types[$this->data[0]['data_name']])) {
      throw new \Exception('We could not figure out which node type you meant. Please specify better or check the list %here.');
    }
    $node = NodeType::load($this->data[0]['data_name']);
    // Set the original.
    $this->setOriginalConfigurations($node);

    // On the node.
    foreach ([
      'readable_name' => 'name',
      'description' => 'description',
      'create_new_revision' => 'new_revision',
      'display_author_and_date_information' => 'display_submitted',
    ] as $part => $field) {
      if (isset($this->data[0][$part])) {
        $node->set($field, $this->data[0][$part]);
      }
    }

    foreach ([
      'sticky_at_top_of_lists' => 'sticky',
      'promoted_to_first_page' => 'promote',
      'publish_automatically' => 'status',
    ] as $part => $field) {
      if (isset($this->data[0][$part])) {
        $this->setOverrideValue($this->data[0][$part], $field, $node);
      }
    }

    // Special rule for preview mode.
    if (isset($this->data[0]['preview_before_submitting'])) {
      $node->set('preview_mode', $this->getPreviewMode());
    }

    if ($node->save()) {
      // Check the diff.
      $url = Url::fromRoute('entity.node_type.edit_form', ['node_type' => $node->id()]);

      $diff = $this->getDiffOfConfigurations($node);
      $this->result[] = $this->t('The node type %node_type (%data_name) has been updated. Its available to check under @link.', [
        '%node_type' => $this->data[0]['readable_name'],
        '%data_name' => $this->data[0]['data_name'],
        '@link' => $url->toString(),
      ]);
      $this->structuredResultData->setEditedConfig($node, $diff);
    }
    else {
      throw new AgentProcessingException('There was an error updating the node type.');
    }
  }

  /**
   * Create a node type.
   */
  public function createNodeType() {
    $node = NodeType::create([
      'type' => $this->data[0]['data_name'],
      'name' => $this->data[0]['readable_name'],
      'description' => $this->data[0]['description'],
      'new_revision' => $this->data[0]['create_new_revision'] ?? TRUE,
      'preview_mode' => $this->getPreviewMode(),
      'display_submitted' => $this->data[0]['display_author_and_date_information'] ?? TRUE,
    ]);
    if ($node->save()) {
      // Special solution, after generation.
      foreach ([
        'sticky_at_top_of_lists' => 'sticky',
        'promoted_to_first_page' => 'promote',
        'publish_automatically' => 'status',
      ] as $part => $field) {
        if (isset($this->data[0][$part])) {
          $this->setOverrideValue($this->data[0][$part], $field, $node);
        }
      }
      $url = Url::fromRoute('entity.node_type.edit_form', ['node_type' => $node->id()]);

      $this->result[] = $this->t('The node type %node_type (%data_name) has been created. Its available to check under @link.', [
        '%node_type' => $this->data[0]['readable_name'],
        '%data_name' => $this->data[0]['data_name'],
        '@link' => $url->toString(),
      ]);
      $this->structuredResultData->setCreatedConfig($node);
    }
    else {
      throw new AgentProcessingException('There was an error creating the node type.');
    }
  }

  /**
   * Get preview mode.
   *
   * @return int
   *   The preview mode.
   */
  public function getPreviewMode(): int {
    switch ($this->data[0]['preview_before_submitting']) {
      case 'required':
        $previewMode = DRUPAL_REQUIRED;
        break;

      case 'disabled':
        $previewMode = DRUPAL_DISABLED;
        break;

      default:
        $previewMode = DRUPAL_OPTIONAL;
        break;
    }
    return $previewMode;
  }

  /**
   * Get all node types.
   *
   * @return array
   *   The node types.
   */
  public function getNodeTypes() {
    $types = NodeType::loadMultiple();
    $nodeTypes = [];
    foreach ($types as $type) {
      $nodeTypes[$type->id()] = $type->label();
    }
    return $nodeTypes;
  }

  /**
   * Get all node types as a string.
   *
   * @return string
   *   The node types.
   */
  public function getNodeTypesAsString() {
    $nodeTypes = $this->getNodeTypes();
    $list = "";
    foreach ($nodeTypes as $dataName => $nodeType) {
      $list .= $nodeType . ' - dataname: ' . $dataName . "\n";
    }
    return $list;
  }

  /**
   * Get all node types as a string with verbose information.
   *
   * @return string
   *   The node types.
   */
  public function getVerboseNodeTypesAsString() {
    $nodeTypes = $this->getNodeTypes();
    $list = "";
    foreach ($nodeTypes as $dataName => $nodeType) {
      // Load the node type.
      $type = NodeType::load($dataName);
      // Show all the configurations.
      $list .= $nodeType . ' - dataname: ' . $dataName . "\n";
      $list .= 'Description: ' . $type->getDescription() . "\n";
      $list .= 'New revision: ' . ($type->get('new_revision') ? 'Yes' : 'No') . "\n";
      $list .= 'Preview mode: ' . $type->getPreviewMode() . "\n";
      $list .= 'Display submitted: ' . ($type->displaySubmitted() ? 'Yes' : 'No') . "\n";
      $list .= "\n";
    }
    return $list;
  }

  /**
   * Function to set override values.
   *
   * @param bool $value
   *   The value.
   * @param string $field_name
   *   The field to override.
   * @param \Drupal\node\Entity\NodeType $node
   *   The node type.
   */
  public function setOverrideValue(bool $value, string $field_name, NodeType $node) {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $node->id());
    if (in_array($field_name, [
      'status',
      'promote',
      'sticky',
    ])) {
      $fields[$field_name]->getConfig($node->id())->setDefaultValue($value)->save();
    }
  }

}
