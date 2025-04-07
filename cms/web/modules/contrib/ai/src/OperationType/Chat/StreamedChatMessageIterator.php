<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Traits\OperationType\EventDispatcherTrait;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  use EventDispatcherTrait;

  /**
   * The iterator.
   *
   * @var \Traversable
   */
  protected $iterator;

  /**
   * The messages.
   *
   * @var array
   *   The stream chat messages.
   */
  protected $messages = [];

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * Constructor.
   */
  public function __construct(\Traversable $iterator) {
    $this->iterator = $iterator;
  }

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void {
    // Create a ChatMessage out of it all.
    $role = '';
    $message_text = '';
    foreach ($this->messages as $message) {
      if (!empty($message->getRole()) && empty($role)) {
        $role = $message->getRole();
      }
      if (!empty($message->getText())) {
        $message_text .= $message->getText();
      }
    }
    $message = [
      'role' => $role,
      'message' => $message_text,
    ];

    // Dispatch the event.
    $event = new PostStreamingResponseEvent($this->requestThreadId, $message, []);
    $this->getEventDispatcher()->dispatch($event, PostStreamingResponseEvent::EVENT_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestThreadId(string $request_thread_id): void {
    $this->requestThreadId = $request_thread_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestThreadId(): string {
    return $this->requestThreadId;
  }

  /**
   * {@inheritdoc}
   */
  public function createStreamedChatMessage(string $role, string $message, array $metadata): StreamedChatMessageInterface {
    $message = new StreamedChatMessage($role, $message, $metadata);
    $this->messages[] = $message;
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamChatMessages(): array {
    return $this->messages;
  }

}
