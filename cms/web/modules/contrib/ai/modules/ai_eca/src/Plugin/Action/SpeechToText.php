<?php

namespace Drupal\ai_eca\Plugin\Action;

/**
 * Describes the ai_eca_execute_speech action.
 *
 * @Action(
 *   id = "ai_eca_execute_stt",
 *   label = @Translation("Speech to Text"),
 *   description = @Translation("Process an audio file through the AI speech-to-text endpoint.")
 * )
 */
class SpeechToText extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $token_value = $this->tokenService->getTokenData($this->configuration['token_input']);
    $audio = file_get_contents($token_value?->getString() ?? '');
    if (!$audio) {
      return;
    }

    $modelData = $this->getModelData();
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\OperationType\SpeechToText\SpeechToTextInterface $provider */
    $provider = $this->loadModelProvider();

    $provider->setConfiguration($this->getModelConfig());
    $response = $provider->speechToText($audio, $modelData['model_id'], ['ai_eca']);

    $this->tokenService->addTokenData($this->configuration['token_result'], $response->getNormalized());
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'speech_to_text';
  }

}
