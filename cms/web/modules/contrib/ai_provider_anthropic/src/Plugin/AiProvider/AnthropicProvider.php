<?php

namespace Drupal\ai_provider_anthropic\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Symfony\Component\Yaml\Yaml;
use WpAi\Anthropic\AnthropicAPI;

/**
 * Plugin implementation of the 'anthropic' provider.
 */
#[AiProvider(
  id: 'anthropic',
  label: new TranslatableMarkup('Anthropic'),
)]
class AnthropicProvider extends AiProviderClientBase implements
  ChatInterface {

  use ChatTrait;

  /**
   * The Anthropic Client.
   *
   * @var \WpAi\Anthropic\AnthropicAPI|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // No complex JSON support.
    if (in_array(AiModelCapability::ChatJsonOutput, $capabilities)) {
      return [
        'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku',
      ];
    }
    // Anthropic hard codes :/.
    if ($operation_type == 'chat') {
      return [
        'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku',
        'claude-3-opus-latest' => 'Claude 3 Opus',
        'claude-3-sonnet-latest' => 'Claude 3 Sonnet',
        'claude-3-haiku-latest' => 'Claude 3 Haiku',
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // If its not configured, it is not usable.
    if (!$this->apiKey && !$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that Anthropic supports its usable.
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_anthropic.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_anthropic')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    $system_prompt = '';
    if ($input instanceof ChatInput) {
      $chat_input = [];
      foreach ($input->getMessages() as $message) {
        // System prompts are a variable.
        if ($message->getRole() == 'system') {
          $system_prompt = $message->getText();
          continue;
        }
        if (count($message->getImages())) {
          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image',
              'source' => [
                'type' => 'base64',
                'media_type' => $image->getMimeType(),
                'data' => $image->getAsBase64EncodedString(''),
              ],
            ];
          }
        }
        $content[] = [
          'type' => 'text',
          // Trim is needed by Anthropic, no trailing spaces allowed.
          'text' => trim($message->getText()),
        ];
        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $content,
        ];
      }
    }
    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    if (!isset($payload['system']) && $system_prompt) {
      $payload['system'] = trim($system_prompt);
    }
    // Unset Max Tokens.
    $max_tokens = $payload['max_tokens'] ?? 1024;
    unset($payload['max_tokens']);
    $headers = [];
    if ($this->chatSystemRole) {
      $payload['system'] = $this->chatSystemRole;
    }
    try {
      /** @var \WpAi\Anthropic\Responses\Response */
      $response_object = $this->client->messages()->maxTokens($max_tokens)->create($payload, $headers);
      $response = $response_object->content;
    }
    catch (\Exception $e) {
      // Try to figure out credit issues.
      if (strpos($e->getMessage(), 'credit balance is too low ') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    if (!isset($response[0]['text'])) {
      throw new AiResponseErrorException('Invalid response from Anthropic');
    }
    $message = new ChatMessage('', $response[0]['text']);

    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'claude-3-5-sonnet-latest',
        'chat_with_image_vision' => 'claude-3-5-sonnet-latest',
        'chat_with_complex_json' => 'claude-3-5-sonnet-latest',
      ],
    ];
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \WpAi\Anthropic\AnthropicAPI
   *   The Anthropic client.
   */
  public function getClient(string $api_key = ''): AnthropicAPI {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Anthropic Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $this->client = new AnthropicAPI($this->apiKey);
    }
  }

}
