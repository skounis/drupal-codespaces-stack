<?php

namespace Drupal\ai_translate\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInterface;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\language\Entity\ConfigurableLanguage;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'chat_translation' provider.
 *
 * The purpose is to implement 'translate_text' operation
 * using more generic 'chat' operation supported by many LLM providers.
 */
#[AiProvider(
  id: 'chat_translation',
  label: new TranslatableMarkup('Chat proxy to LLM'),
)]
class ChatTranslationProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  TranslateTextInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $manager;

  /**
   * Configuration of default chat provider.
   *
   * @var array
   */
  protected array $chatConfiguration;

  /**
   * Lazy-loaded provider that actually performs text translation.
   *
   * @var \Drupal\ai\Plugin\ProviderProxy
   */
  protected ProviderProxy $realTranslator;

  /**
   * The Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->manager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (empty($this->configFactory->get('ai_translate.settings')->get('prompt'))) {
      return FALSE;
    }
    if (!isset($this->chatConfiguration)) {
      $defaultProviders = $this->config->get('default_providers');
      if (empty($defaultProviders)) {
        $this->chatConfiguration = [];
      }
      $this->chatConfiguration = $defaultProviders['chat'] ?? [];
    }
    return !empty($this->chatConfiguration)
      && in_array($operation_type, $this->getSupportedOperationTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'translate_text',
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
    return $this->configFactory->get('ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $defaultProviders = $this->getConfig()->get('default_providers');
    if (empty($defaultProviders['chat'])) {
      $this->messenger()->addWarning($this->t('@plugin requires a default chat provider to be set.',
        ['@plugin' => $this->getPluginId()]));
      return [];
    }
    $chatProvider = $this->manager->createInstance($defaultProviders['chat']['provider_id']);
    $models = $chatProvider->getConfiguredModels('chat');
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // This provider does not have model settings.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {}

  /**
   * {@inheritdoc}
   */
  public function translateText(TranslateTextInput $input, string $model_id, array $options = []): TranslateTextOutput {
    $text = $input->getText();

    // We can guess source, but not target language.
    $targetLanguage = ConfigurableLanguage::load($input->getTargetLanguage());
    if (!$targetLanguage) {
      // @todo TranslateText-specific exception, documented in
      // TranslateTextInterface::translateText() docblock.
      $this->loggerFactory->get('ai_translate')->warning(
        $this->t('Unable to guess target language, code @langcode',
          ['@langcode' => $input->getTargetLanguage()]));
      return new TranslateTextOutput('', '', '');
    }

    $aiConfig = $this->configFactory->get('ai_translate.settings');
    $prompt = $aiConfig->get($targetLanguage->getId() . '_prompt');
    if (empty($prompt)) {
      $prompt = $aiConfig->get('prompt');
    }
    $context = [
      'dest_lang' => $targetLanguage->getId(),
      'dest_lang_name' => $targetLanguage->getName(),
      'input_text' => $text,
    ];
    try {
      $sourceLanguage = ConfigurableLanguage::load($input->getSourceLanguage());
      if ($sourceLanguage) {
        $context['source_lang'] = $sourceLanguage->getId();
        $context['source_lang_name'] = $sourceLanguage->getName();
      }
    }
    // Ignore failure to load source language.
    catch (\AssertionError) {
    }
    $promptText = $this->twig->renderInline($prompt, $context);
    try {
      $this->setChatSystemRole('You are a helpful translator.');
      $messages = new ChatInput([
        new chatMessage('user', $promptText),
      ]);

      $this->loadTranslator($messages);
      /** @var /Drupal\ai\OperationType\Chat\ChatOutput $message */
      $message = $this->realTranslator->chat($messages, $this->chatConfiguration['model_id']);
    }
    catch (GuzzleException $exception) {
      // Error handling for the API call.
      $this->loggerFactory->get('ai_translate')
        ->warning($exception->getMessage());
      return new TranslateTextOutput('', '', '');
    }

    return new TranslateTextOutput($message->getNormalized()->getText(),
      $message->getRawOutput(), []);
  }

  /**
   * Load real translator and its configuration.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy|null
   *   Real provider or NULL on failure.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadTranslator(ChatInput $messages) :? ProviderProxy {
    if (isset($this->realTranslator)) {
      return $this->realTranslator;
    }
    $chatConfig = $this->manager->getDefaultProviderForOperationType('chat');

    // Allow other modules to take over.
    $this->moduleHandler->alter('ai_translate_translation', $messages,
      $chatConfig['provider_id'], $chatConfig['model_id']);

    $this->realTranslator = $this->manager->createInstance($chatConfig['provider_id'],
      $chatConfig);
    $this->chatConfiguration = $chatConfig;
    return $this->realTranslator;
  }

}
