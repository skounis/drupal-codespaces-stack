<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Describes the ai_eca_execute_embedding action.
 *
 * @Action(
 *   id = "ai_eca_execute_embedding",
 *   label = @Translation("Embedding"),
 *   description = @Translation("Generate a text embedding from an input.")
 * )
 */
class Embedding extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $modelData = $this->getModelData();
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $provider */
    $provider = $this->loadModelProvider();

    $token_value = $this->tokenService->getTokenData($this->configuration['token_input']);
    $provider->setConfiguration($this->getModelConfig());
    $input = new EmbeddingsInput($token_value?->getString() ?? '');
    $response = $provider->embeddings($input, $modelData['model_id'])->getNormalized();

    $dto = DataTransferObject::create((array) $response);
    $this->tokenService->addTokenData($this->configuration['token_result'], $dto);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'embeddings';
  }

}
