<?php

declare(strict_types=1);

namespace Drupal\ai_agents\PluginBase;

use Drupal\Component\Plugin\PluginBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentValidationInterface;

/**
 * Base class for ai_agent_validation plugins.
 */
abstract class AiAgentValidationPluginBase extends PluginBase implements AiAgentValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

}
