<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * For collecting the results post streaming.
 *
 * This event should be used in conjunction with the PostGenerateResponseEvent
 * using the request thread id to connect the two events. There is no
 * manipulation of the data in this event, it is just for collecting the final
 * results.
 */
class PostStreamingResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.post_streaming_response';

  /**
   * The request thread id id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected $role;

  /**
   * The output for the request.
   *
   * @var mixed
   *   The output for the request.
   */
  protected $output;

  /**
   * The metadata to store for the request.
   *
   * @var array
   */
  protected array $metadata;

  /**
   * Constructs the object.
   *
   * @param string $request_thread_id
   *   The unique request thread id.
   * @param mixed $output
   *   The output for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(string $request_thread_id, $output, array $metadata = []) {
    $this->requestThreadId = $request_thread_id;
    $this->output = $output;
    $this->metadata = $metadata;
  }

  /**
   * Gets the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId() {
    return $this->requestThreadId;
  }

  /**
   * Gets the output.
   *
   * @return mixed
   *   The output.
   */
  public function getOutput() {
    return $this->output;
  }

  /**
   * Get all the metadata.
   *
   * @return array
   *   All the metadata.
   */
  public function getAllMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set all metadata replacing existing contents.
   *
   * @param array $metadata
   *   All the metadata.
   */
  public function setAllMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Get specific metadata by key.
   *
   * @param string $metadata_key
   *   The key of the metadata to return.
   *
   * @return mixed
   *   The metadata for the provided key.
   */
  public function getMetadata(string $metadata_key): mixed {
    return $this->metadata[$metadata_key];
  }

  /**
   * Add to the metadata by key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setMetadata(string $key, mixed $value): void {
    $this->metadata[$key] = $value;
  }

}
