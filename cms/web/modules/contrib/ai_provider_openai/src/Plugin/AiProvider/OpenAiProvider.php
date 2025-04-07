<?php

namespace Drupal\ai_provider_openai\Plugin\AiProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai\OperationType\Moderation\ModerationInterface;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInterface;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_openai\OpenAiChatMessageIterator;
use Drupal\ai_provider_openai\OpenAiHelper;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'openai' provider.
 */
#[AiProvider(
  id: 'openai',
  label: new TranslatableMarkup('OpenAI'),
)]
class OpenAiProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  ChatInterface,
  ModerationInterface,
  EmbeddingsInterface,
  TextToSpeechInterface,
  SpeechToTextInterface,
  TextToImageInterface {

  use ChatTrait;

  /**
   * The OpenAI Client.
   *
   * @var \OpenAI\Client|null
   */
  protected $client;

  /**
   * The helper to use.
   *
   * @var \Drupal\ai_provider_openai\OpenAiHelper
   */
  protected OpenAiHelper $openAiHelper;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool|null
   */
  protected bool|null $moderation = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->openAiHelper = $container->get('ai_provider_openai.helper');
    return $parent_instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Load all models, and since OpenAI does not provide information about
    // which models does what, we need to hard code it in a helper function.
    $this->loadClient();
    return $this->getModels($operation_type, $capabilities);
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // If its not configured, it is not usable.
    if (!$this->apiKey && !$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    // If its one of the bundles that OpenAI supports its usable.
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
      'embeddings',
      'moderation',
      'text_to_image',
      'text_to_speech',
      'speech_to_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_openai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_openai')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // If its GPT 3.5 the max tokens are 2048.
    if (preg_match('/gpt-3.5-turbo/', $model_id)) {
      $generalConfig['max_tokens']['default'] = 2048;
    }
    if ($model_id == 'dall-e-3') {
      $generalConfig['quality'] = [
        'label' => 'Quality',
        'description' => 'The quality of the images that will be generated.',
        'type' => 'string',
        'default' => 'standard',
        'required' => FALSE,
        'constraints' => [
          'options' => [
            'hd',
            'standard',
          ],
        ],
      ];
      $generalConfig['size']['default'] = '1024x1024';
      $generalConfig['size']['constraints']['options'] = [
        '1024x1024',
        '1024x1792',
        '1792x1024',
      ];
      $generalConfig['style'] = [
        'label' => 'Style',
        'description' => 'The style of the images that will be generated.',
        'type' => 'string',
        'default' => 'vivid',
        'required' => FALSE,
        'constraints' => [
          'options' => [
            'vivid',
            'natural',
          ],
        ],
      ];
    }
    if ($model_id == 'text-embedding-3-large') {
      $generalConfig['dimensions']['default'] = 3072;
    }
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
   * @return \OpenAI\Client
   *   The OpenAI client.
   */
  public function getClient(string $api_key = ''): Client {
    // If the moderation is not set, we load it from the configuration.
    if (is_null($this->moderation)) {
      $this->moderation = $this->getConfig()->get('moderation');
    }
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the OpenAI Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
      $client = \OpenAI::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient($this->httpClient);

      // If the configuration has a custom endpoint, we set it.
      if (!empty($this->getConfig()->get('host'))) {
        $client->withBaseUri($this->getConfig()->get('host'));
      }

      $this->client = $client->make();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      // Add a system role if wanted.
      if ($this->chatSystemRole) {
        // If its o1 or o3 in it, we add it as a user message.
        if (preg_match('/(o1|o3)/i', $model_id)) {
          $chat_input[] = [
            'role' => 'user',
            'content' => $this->chatSystemRole,
          ];
        }
        else {
          $chat_input[] = [
            'role' => 'system',
            'content' => $this->chatSystemRole,
          ];
        }
      }
      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
      foreach ($input->getMessages() as $message) {
        $content = [
          [
            'type' => 'text',
            'text' => $message->getText(),
          ],
        ];
        if (count($message->getImages())) {
          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image_url',
              'image_url' => [
                'url' => $image->getAsBase64EncodedString(),
              ],
            ];
          }
        }
        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $content,
        ];
      }
    }
    // Moderation check - tokens are still there using json.
    $this->moderationEndpoints(json_encode($chat_input));
    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    try {
      if ($this->streamed) {
        $response = $this->client->chat()->createStreamed($payload);
        $message = new OpenAiChatMessageIterator($response);
      }
      else {
        $response = $this->client->chat()->create($payload)->toArray();
        $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content']);
      }
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $this->loadClient();
    // Normalize the prompt if needed.
    if ($input instanceof ModerationInput) {
      $input = $input->getPrompt();
    }
    $payload = [
      'model' => $model_id ?? 'omni-moderation-latest',
      'input' => $input,
    ] + $this->configuration;
    $response = $this->client->moderations()->create($payload)->toArray();
    $normalized = new ModerationResponse($response['results'][0]['flagged'], $response['results'][0]['category_scores']);
    return new ModerationOutput($normalized, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    // Moderation.
    $this->moderationEndpoints($input);
    // The send.
    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->images()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    $images = [];

    if (empty($response['data'][0])) {
      throw new AiResponseErrorException('No image data found in the response.');
    }
    foreach ($response['data'] as $data) {
      if (isset($this->configuration['response_format']) && $this->configuration['response_format'] === 'b64_json') {
        $images[] = new ImageFile(base64_decode($data['b64_json']), 'image/png', 'dalle.png');
      }
      else {
        $images[] = new ImageFile(file_get_contents($data['url']), 'image/png', 'dalle.png');
      }
    }
    return new TextToImageOutput($images, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }
    // Moderation.
    $this->moderationEndpoints($input);
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->speech($payload);
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    $output = new AudioFile($response, 'audio/mpeg', 'openai.mp3');

    // Return a normalized response.
    return new TextToSpeechOutput([$output], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $input = fopen($path, 'r');
    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->transcribe($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Moderation.
    $this->moderationEndpoints($input);
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->embeddings()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'gpt-4o',
        'chat_with_image_vision' => 'gpt-4o',
        'chat_with_complex_json' => 'gpt-4o',
        'text_to_image' => 'dall-e-3',
        'embeddings' => 'text-embedding-3-small',
        'moderation' => 'omni-moderation-latest',
        'text_to_speech' => 'tts-1-hd',
        'speech_to_text' => 'whisper-1',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function postSetup(): void {
    // Throw an error on installation with rate limit.
    $this->openAiHelper->testRateLimit($this->loadApiKey());
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return match($model_id) {
      'text-embedding-ada-002', 'text-embedding-3-small' => 1536,
      'text-embedding-3-large' => 3072,
      default => 0,
    };
  }

  /**
   * Moderation endpoints to run before the normal call.
   *
   * @throws \Drupal\ai\Exception\AiUnsafePromptException
   */
  public function moderationEndpoints(string $prompt): void {
    $this->getClient();
    // If moderation is disabled, we skip this.
    if (!$this->moderation) {
      return;
    }
    $payload = [
      'model' => 'omni-moderation-latest',
      'input' => $prompt,
    ] + $this->configuration;
    try {
      $response = $this->client->moderations()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    if (!empty($response['results'][0]['flagged'])) {
      throw new AiUnsafePromptException('The prompt was flagged by the moderation model.');
    }
  }

  /**
   * Obtains a list of models from OpenAI and caches the result.
   *
   * This method does its best job to filter out deprecated or unused models.
   * The OpenAI API endpoint does not have a way to filter those out yet.
   *
   * @param string $operation_type
   *   The bundle to filter models by.
   * @param array $capabilities
   *   The capabilities to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type, $capabilities): array {
    $models = [];

    $cache_key = 'openai_models_' . $operation_type . '_' . Crypt::hashBase64(Json::encode($capabilities));
    $cache_data = $this->cacheBackend->get($cache_key);

    if (!empty($cache_data)) {
      return $cache_data->data;
    }

    $list = $this->client->models()->list()->toArray();

    foreach ($list['data'] as $model) {
      if ($model['owned_by'] === 'openai-dev') {
        continue;
      }

      if (!preg_match('/^(gpt|text|o1|embed|tts|whisper|dall-e)/i', $model['id'])) {
        continue;
      }

      // Skip unused. hidden, or deprecated models.
      if (preg_match('/(search|similarity|edit|1p|instruct)/i', $model['id'])) {
        continue;
      }

      if (in_array($model['id'], ['tts-1-hd-1106', 'tts-1-1106'])) {
        continue;
      }

      // Bundle specific logic.
      switch ($operation_type) {
        case 'chat':
          if (!preg_match('/^(gpt|text|o1)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'embeddings':
          if (!preg_match('/^(text-embedding)/i', trim($model['id']))) {
            continue 2;
          }
          break;

        case 'moderation':
          if (!preg_match('/^(text-moderation|omni-moderation)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'image_to_text':
          if (!preg_match('/^(gpt-4o|gpt-4-turbo|vision)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_image':
          if (!preg_match('/^(dall-e|clip)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'speech_to_text':
          if (!preg_match('/^(whisper)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_speech':
          if (!preg_match('/^(tts)/i', $model['id'])) {
            continue 2;
          }
          break;
      }

      // Filter models.
      if (in_array(AiModelCapability::ChatWithImageVision, $capabilities) && !preg_match('/^(gpt-4o|gpt-4-turbo|vision)/i', $model['id'])) {
        continue;
      }
      // Allow gpt-4o and gpt-4-turbo, but not gpt-4o-mini.
      if (in_array(AiModelCapability::ChatJsonOutput, $capabilities) && (!preg_match('/^(gpt-4o|o1|gpt-4-turbo)/i', $model['id']) || preg_match('/(mini)/i', $model['id']))) {
        continue;
      }
      // Don't allow audio or video for now.
      if (in_array(AiModelCapability::ChatWithAudio, $capabilities)) {
        continue;
      }
      if (in_array(AiModelCapability::ChatWithVideo, $capabilities)) {
        continue;
      }

      $models[$model['id']] = $model['id'];
    }

    if ($operation_type == 'moderation') {
      $models['text-moderation-latest'] = 'text-moderation-latest';
      $models['omni-moderation-latest'] = 'omni-moderation-latest';
    }

    if (!empty($models)) {
      asort($models);
      $this->cacheBackend->set($cache_key, $models);
    }

    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    // @todo This corresponds to OpenAI API.
    // Ideally, we should provide real number per model.
    return 8191;
  }

}
