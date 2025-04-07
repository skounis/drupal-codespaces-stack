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
  id: 'json_exists_validation',
  label: new TranslatableMarkup('JSON exists validation'),
)]
class JsonExistsValidation extends AiAgentValidationPluginBase implements ContainerFactoryPluginInterface {

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
      if (is_array($this->promptJsonDecoder->decode($data))) {
        return TRUE;
      }
    }

    throw new AgentRetryableValidationException('The LLM response failed validation.', 0, NULL, 'You MUST only provide a RFC8259 compliant JSON response.');
  }

}
