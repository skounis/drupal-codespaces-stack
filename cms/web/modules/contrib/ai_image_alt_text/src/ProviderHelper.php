<?php

namespace Drupal\ai_image_alt_text;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;

class ProviderHelper {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   The AI provider manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AiProviderPluginManager $aiProviderManager) {
    $this->configFactory = $configFactory;
    $this->aiProviderManager = $aiProviderManager;
  }

  /**
   * Get the set provider.
   *
   * @return array|null
   *   The set provider.
   */
  public function getSetProvider() {
    // Check if there is a preferred model.
    $preferred_model = $this->configFactory->get('ai_image_alt_text.settings')->get('ai_model');
    $provider = NULL;
    $model = NULL;
    if ($preferred_model) {
      $provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
      $model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
    } else {
      // Get the default provider.
      $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat_with_image_vision');
      if (empty($default_provider['provider_id'])) {
        // If we got nothing return NULL.
        return NULL;
      }
      $provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
      $model = $default_provider['model_id'];
    }
    return [
      'provider_id' => $provider,
      'model_id' => $model,
    ];
  }

}
