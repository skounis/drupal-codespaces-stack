<?php

declare(strict_types=1);

namespace Drupal\ai_agents\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_agents\Attribute\AiAgentValidation;
use Drupal\ai_agents\PluginInterfaces\AiAgentValidationInterface;

/**
 * AiAgentValidation plugin manager.
 */
final class AiAgentValidationPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiAgentValidation', $namespaces, $module_handler, AiAgentValidationInterface::class, AiAgentValidation::class);
    $this->alterInfo('ai_agent_validation_info');
    $this->setCacheBackend($cache_backend, 'ai_agent_validation_plugins');
  }

  /**
   * Performs validation of an Agent return as defined by its yaml file.
   *
   * @param array $settings
   *   The yaml validation definition array: [pluginId => validationMethodName].
   * @param mixed $data
   *   The Agent return to validate.
   *
   * @return bool
   *   The result of the validation. Default is FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\ai_agents\Exception\AgentRetryableValidationException
   */
  public function validate(array $settings, mixed $data): bool {
    $return = FALSE;

    if (isset($settings[0])) {
      if ($definition = $this->getDefinition($settings[0], FALSE)) {

        /** @var \Drupal\ai_agents\PluginInterfaces\AiAgentValidationInterface $plugin */
        if ($plugin = $this->createInstance($settings[0], $definition)) {
          if (isset($settings[1]) && method_exists($plugin, $settings[1])) {
            $return = call_user_func([$plugin, $settings[1]], $data);
          }
          else {
            $return = $plugin->defaultValidation($data);
          }
        }
      }
    }

    return $return;
  }

}
