<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * For streaming chat message.
 */
interface StreamedChatMessageIteratorInterface extends \IteratorAggregate {

  public function __construct(\IteratorAggregate $iterator);

  /**
   * Set an request thread id.
   *
   * @param string $request_thread_id
   *   The request thread id.
   */
  public function setRequestThreadId(string $request_thread_id): void;

  /**
   * Get the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId(): string;

  /**
   * Sets on stream chat message.
   *
   * @param string $role
   *   The role.
   * @param string $message
   *   The message.
   * @param array $metadata
   *   The metadata.
   *
   * @return \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface
   *   The streamed chat message.
   */
  public function createStreamedChatMessage(string $role, string $message, array $metadata): StreamedChatMessageInterface;

  /**
   * Gets the stream chat messages.
   *
   * @return array
   *   The stream chat messages.
   */
  public function getStreamChatMessages(): array;

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void;

}
