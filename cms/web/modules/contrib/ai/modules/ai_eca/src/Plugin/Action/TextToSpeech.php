<?php

namespace Drupal\ai_eca\Plugin\Action;

/**
 * Describes the ai_eca_execute_tts action.
 *
 * @Action(
 *   id = "ai_eca_execute_tts",
 *   label = @Translation("Text to Speech"),
 *   description = @Translation("Run text through the AI text-to-speech model.")
 * )
 */
class TextToSpeech extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $modelData = $this->getModelData();
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface $provider */
    $provider = $this->loadModelProvider();

    $token_value = $this->tokenService->getTokenData($this->configuration['token_input']);
    $provider->setConfiguration($this->getModelConfig());

    $response = $provider->textToSpeech($token_value?->getString() ?? '', $modelData['model_id'], ['ai_eca']);

    $normalized = $response->getNormalized();
    $file = $normalized[0]->getAsFileEntity('public://', 'audio.mp3');
    $path = $file->createFileUrl(FALSE);

    $this->tokenService->addTokenData($this->configuration['token_result'], $path);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'text_to_speech';
  }

}
