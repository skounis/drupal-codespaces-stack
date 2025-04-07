<?php

namespace Drupal\ai_agents_form_integration\Batch;

use Drupal\Core\Url;
use Drupal\ai_agents\Task\Task;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Batch operations for content type generation.
 */
class ContentTypeGeneration {

  /**
   * Generate field type.
   */
  public static function generateFieldType($fieldPrompt, $bundle, $vocabularyGenerate, &$context) {
    if ($vocabularyGenerate && !empty($fieldPrompt['vocabulary'])) {
      $tax_agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('taxonomy_agent');
      $task = new Task("Can you generate the vocabulary " . $fieldPrompt['vocabulary']);
      $tax_agent->setTask($task);
      $tax_agent->determineSolvability();
      $tax_agent->solve();
    }
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('field_type_agent');
    $prompt = "Based on the following possible prompt can you create the the field on the entity type node and the bundle $bundle:";
    $prompt .= "Create the field " . $fieldPrompt['field_name'] . " of type " . $fieldPrompt['fieldType'] . " with the description " . $fieldPrompt['description'];
    // Make it required.
    if (!empty($fieldPrompt['required'])) {
      $prompt .= " and make it required";
    }
    $task = new Task($prompt);
    $agent->setTask($task);
    $provider_service = \Drupal::service('ai.provider');
    $default = $provider_service->getDefaultProviderForOperationType('chat');
    $agent->setAiProvider($provider_service->createInstance($default['provider_id']));
    $agent->setModelName($default['model_id']);
    $agent->setAiConfiguration([]);
    $agent->setCreateDirectly(TRUE);
    $solvability = $agent->determineSolvability();
    if (!$solvability) {
      $context['results']['error'] = "The AI agent could not solve the task.";
      return;
    }
    $context['results']['bundle'] = $bundle;
    $agent->solve();
  }

  /**
   * Generate content type blueprint finished.
   */
  public static function generateContentTypeFinished($success, $results, $operations) {
    // Remove the body field. Solution to not break edit page.
    $config = \Drupal::entityTypeManager()->getStorage('field_config')->load('node.' . $results['bundle'] . '.body');
    if ($config) {
      $config->delete();
    }
    $route = 'entity.node_type.collection';
    return new RedirectResponse(Url::fromRoute($route)->toString());
  }

}
