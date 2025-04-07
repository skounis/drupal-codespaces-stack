<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Describes the ai_eca_execute_moderation action.
 *
 * @Action(
 *   id = "ai_eca_execute_moderation",
 *   label = @Translation("Moderation"),
 *   description = @Translation("Determine if a piece of text violates any usage policies.")
 * )
 */
class Moderation extends AiActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $modelData = $this->getModelData();
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\OperationType\Moderation\ModerationInterface $provider */
    $provider = $this->loadModelProvider();

    $token_value = $this->tokenService->getTokenData($this->configuration['token_input']);
    $input = new ModerationInput($token_value?->getString() ?? '');
    $response = $provider->moderation($input, $modelData['model_id'])->getNormalized();

    $dto = DataTransferObject::create([
      'flagged' => $response->isFlagged(),
      'information' => $response->getInformation(),
    ]);
    $this->tokenService->addTokenData($this->configuration['token_result'], $dto);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'moderation';
  }

}
