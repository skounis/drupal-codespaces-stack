<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that is for LLMs simple office hours field rules.
 */
class OfficeHours extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can extract office hours from text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context text return the office hours that you can find.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\n\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"day\": \"1 for monday, 2 for tuesday and so on\", \"starthours\": \"opening hour in hi format, so 16:00 would be 1600\", \"endhours\": \"closing hour in hi format, so 20:00 would be 2000\"}].\n\nOnly give back the days they are open.";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Has to be valid day.
    if (!empty($value['day']) && !empty($value['starthours']) && !empty($value['endhours'])) {
      return TRUE;
    }
    // Otherwise it is not ok.
    return FALSE;
  }

}
