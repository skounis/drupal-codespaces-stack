<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for chat output.
 */
class ChatOutput implements OutputInterface {

  /**
   * The chat message.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   */
  private ChatMessage|StreamedChatMessageIteratorInterface $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $normalized
   *   The chat message.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   */
  public function __construct(ChatMessage|StreamedChatMessageIteratorInterface $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the new chat message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The text string.
   */
  public function getNormalized(): ChatMessage|StreamedChatMessageIteratorInterface {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
