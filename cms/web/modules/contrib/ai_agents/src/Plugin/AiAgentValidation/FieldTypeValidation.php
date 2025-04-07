<?php

namespace Drupal\ai_agents\Plugin\AiAgentValidation;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_agents\Attribute\AiAgentValidation;
use Drupal\ai_agents\Exception\AgentRetryableValidationException;
use Drupal\ai_agents\PluginBase\AiAgentValidationPluginBase;

/**
 * The field types agent.
 */
#[AiAgentValidation(
  id: 'field_type_validation',
  label: new TranslatableMarkup('Field Type Agent Validation'),
)]
class FieldTypeValidation extends AiAgentValidationPluginBase {

  /**
   * {@inheritDoc}
   */
  public function defaultValidation(mixed $data): bool {
    $text = NULL;

    if ($data instanceof ChatMessage) {
      $text = $data->getText();
    }
    elseif (is_string($data)) {
      $text = $data;
    }
    if ($text) {
      if (Json::decode($text)) {

        // Basic validation to serve as an example of how to implement.
        return TRUE;
      }
    }

    throw new AgentRetryableValidationException('The LLM response failed validation.', 0, NULL, 'You MUST only provide a RFC8259 compliant JSON response.');
  }

}
