<?php

namespace Drupal\ai_agents_form_integration\Batch;

use Drupal\Core\Url;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Task\Task;
use Drupal\file\Entity\File;
use Drupal\webform\Entity\Webform;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Batch job on webforms.
 */
class WebformBatchJob {

  /**
   * Batch function to create a description for the webform.
   *
   * @param int $file_id
   *   The file id.
   * @param string $text
   *   The text prompt.
   * @param string $open
   *   If the webform is open.
   * @param string $description
   *   The webform description.
   * @param array $context
   *   The batch context.
   */
  public static function createDescription(?int $file_id, string $text, string $open, string $description, array &$context) {
    $context['results']['ai_description'] = $text;
    $context['results']['open'] = $open;
    $context['results']['description'] = $description;
    // If no file exists, we just pass the text as the description.
    if (!$file_id) {
      $context['results']['ai_description'] = $text;
      return;
    }
    // Base 64 encode the image.
    $file = File::load($file_id);

    $prompt = "Create a description for that describes the webform in as much detail as possible based on the image.\n";
    $prompt .= "For each of the webform elements describe what type of web element it is, the title of it, the description of it, if its required and any other important information.\n";
    $prompt .= "If its checkboxes, radio buttons, or select lists, describe the options available.\n";
    $prompt .= "If the options are just bogus, like e.g. 'Option 1', 'Choice 2', etc. it means that it wants you to make up some options for it based on the context of the field.\n";
    $prompt .= "Describe each of the options above on a row and with a new line and then double new line between each element.\n";
    $prompt .= "For example:\n";
    $prompt .= "----------------------------------------\n";
    $prompt .= "Type: Textfield\n";
    $prompt .= "Title: First Name\n";
    $prompt .= "Description: Enter your first name\n";
    $prompt .= "Required: Yes\n\n";
    $prompt .= "Type: Select List\n";
    $prompt .= "Title: Dietary Constraints\n";
    $prompt .= "Description: Select any dietary constraints you have\n";
    $prompt .= "Options: Vegetarian, Vegan, Gluten Free\n";
    $prompt .= "Required: No\n";
    $prompt .= "----------------------------------------\n";
    $images = [];

    if (!in_array($file->getMimeType(), [
      'image/jpeg',
      'image/png',
      'image/gif',
    ]) && class_exists('\Drupal\unstructured\Formatters\MarkdownFormatter')) {
      // Run unstructured data extraction.
      $data = \Drupal::service('unstructured.api')->structure($file);
      // @codingStandardsIgnoreLine
      $format = new \Drupal\unstructured\Formatters\MarkdownFormatter();
      $gotten_text = $format->format($data, "all");
      $file_context = '';
      if (isset($gotten_text[0])) {
        $file_context = $gotten_text[0];
      }
      // Then let AI create a description from that.
      $prompt .= "\n\nThe following is a textual representation of the content from a file that was uploaded. Use this as context for describing the webform:\n";
      $prompt .= "--------------------------------------------\n";
      $prompt .= $file_context;
      $prompt .= "\n--------------------------------------------\n";

    }
    else {
      // Prepare image data for prompt.
      $prompt .= "\n\nUse the attached image as context for describing the webform.\n\n";

      $image = new ImageFile();
      $image->setFileFromFile($file);
      $images[] = $image;
    }

    // Write a prompt.
    if ($text) {
      $prompt .= "\n\n";
      $prompt .= "You can also take the following text prompt into consideration when trying to generate the description:\n";
      $prompt .= "--------------------------------------------\n";
      $prompt .= $text;
      $prompt .= "\n--------------------------------------------\n";
    }

    // Send the request.
    $message = new ChatMessage("user", $prompt, $images);
    $input = new ChatInput([$message]);
    $provider_service = \Drupal::service('ai.provider');
    $default = $provider_service->getDefaultProviderForOperationType('chat');
    $result = $provider_service->createInstance($default['provider_id'])->chat($input, $default['model_id'])->getNormalized();
    $ai_description = $result->getText();

    $context['results']['ai_description'] = $ai_description;
  }

  /**
   * Batch function to run the agent.
   *
   * @param string $webform_id
   *   The webform id.
   * @param array $context
   *   The batch context.
   */
  public static function runAgent(string $webform_id, array &$context) {
    $webformEntity = Webform::load($webform_id);
    $yaml = $webformEntity->toArray();
    $webformEntity->delete();

    // Get the description.
    $prompt = 'Generate a webform with id "' . $yaml['id'] . '" and title "' . $yaml['title'] . "\"\n";
    $prompt .= "Use the following prompt to generate the form:\n" . $context['results']['ai_description'] . "\n\n";
    $prompt .= "If no webform internal link is in the prompt, you should use the internal link /webform/" . $yaml['id'] . "\n\n";

    // Load the plugin manager.
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('webform_agent');

    $task = new Task($prompt);
    $agent->setTask($task);
    $agent->setFixedData([
      'webform_id' => $yaml['id'],
      'webform_title' => $yaml['title'],
      'webform_open' => $context['results']['open'],
      'webform_description' => $context['results']['description'],
    ]);
    $provider_service = \Drupal::service('ai.provider');
    $default = $provider_service->getDefaultProviderForOperationType('chat');
    $agent->setAiProvider($provider_service->createInstance($default['provider_id']));
    $agent->setModelName($default['model_id']);
    $agent->setAiConfiguration([]);
    $solvability = $agent->determineSolvability();
    if ($solvability !== AiAgentInterface::JOB_SOLVABLE) {
      $context['results']['error'] = "The AI agent could not solve the task.";
    }
    else {
      // Solve it.
      $agent->solve();
      $context['results']['webform_id'] = $yaml['id'];
    }
  }

  /**
   * Batch finished function.
   *
   * @param bool $success
   *   If the batch was successful.
   * @param array $results
   *   The results of the batch.
   * @param array $operations
   *   The operations of the batch.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    $webform_id = $results['webform_id'];
    // Reroute to the webform.
    $url = Url::fromRoute('entity.webform.canonical', ['webform' => $webform_id]);
    return new RedirectResponse($url->toString());
  }

}
