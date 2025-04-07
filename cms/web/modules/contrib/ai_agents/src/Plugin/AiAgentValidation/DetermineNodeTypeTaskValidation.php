<?php

namespace Drupal\ai_agents\Plugin\AiAgentValidation;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_agents\Attribute\AiAgentValidation;
use Drupal\ai_agents\Exception\AgentRetryableValidationException;
use Drupal\ai_agents\PluginBase\AiAgentValidationPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The field types agent.
 */
#[AiAgentValidation(
  id: 'determine_node_type_task_validation',
  label: new TranslatableMarkup('Determine node type task validation'),
)]
class DetermineNodeTypeTaskValidation extends AiAgentValidationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The allowed actions.
   *
   * @var array
   */
  protected array $actionsPossible = [
    'create',
    'edit',
    'delete',
    'information',
    'fail',
  ];

  /**
   * Constructor.
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.prompt_json_decode'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function defaultValidation(mixed $data): bool {
    if ($data instanceof ChatMessage || $data instanceof StreamedChatMessageIteratorInterface) {
      $extracted = $this->promptJsonDecoder->decode($data);
      if (isset($extracted[0]['action']) && in_array($extracted[0]['action'], $this->actionsPossible)) {
        return TRUE;
      }
    }

    throw new AgentRetryableValidationException('The LLM response failed validation.', 0, NULL, 'You MUST only provide a RFC8259 compliant JSON response with an action of one of the following type: ' . implode(', ', $this->actionsPossible));
  }

}
