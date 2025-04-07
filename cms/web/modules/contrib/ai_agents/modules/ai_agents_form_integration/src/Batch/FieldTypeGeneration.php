<?php

namespace Drupal\ai_agents_form_integration\Batch;

use Drupal\Core\Url;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Task\Task;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * These are the batch jobs for generating field types.
 */
class FieldTypeGeneration {

  /**
   * Generate context.
   */
  public static function generateField($prompt, $entity, $bundle, &$context) {
    $context['results']['entity_type'] = $entity;
    $context['results']['bundle'] = $bundle;
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('field_type_agent');

    $task = new Task($prompt);
    $agent->setTask($task);
    $provider_service = \Drupal::service('ai.provider');
    $default = $provider_service->getDefaultProviderForOperationType('chat');
    $agent->setAiProvider($provider_service->createInstance($default['provider_id']));
    $agent->setModelName($default['model_id']);
    $agent->setAiConfiguration([]);
    $solvability = $agent->determineSolvability();
    if ($solvability == AiAgentInterface::JOB_SOLVABLE) {
      $agent->solve();
    }
    elseif ($solvability == AiAgentInterface::JOB_INFORMS) {
      \Drupal::messenger()->addMessage('Information: ' . $agent->inform());
    }
    elseif ($solvability == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
      \Drupal::messenger()->addMessage('Question: ' . $agent->answerQuestion());
    }
    elseif ($solvability == AiAgentInterface::JOB_NEEDS_ANSWERS) {
      $questions = $agent->askQuestion();
      if ($questions && is_array($questions)) {
        \Drupal::messenger()->addMessage('Question: ' . implode("\n", $questions));
      }
    }
    else {
      \Drupal::messenger()->addMessage('Error: Sorry, I could not solve this, could you please rephrase?');
    }

  }

  /**
   * Finished.
   */
  public static function finished($success, $results, $operations) {
    $route = 'entity.' . $results['entity_type'] . '.field_ui_fields';
    // Get the parameters for the route.
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName($route);
    $parameters = $route->compile()->getPathVariables();
    $arguments = [];
    // If a bundle exists.
    if (isset($parameters[0])) {
      $arguments[$parameters[0]] = $results['bundle'];
    }

    return new RedirectResponse(Url::fromRoute($route, $arguments)->toString());
  }

}
