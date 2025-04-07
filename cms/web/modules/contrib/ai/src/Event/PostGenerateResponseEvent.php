<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Changes or Exceptions to the output of a AI request can be done here.
 */
class PostGenerateResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.post_generate_response';

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The provider to process.
   *
   * @var string
   */
  protected $providerId;

  /**
   * The configuration of the provider.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The operation type for the request.
   *
   * @var string
   */
  protected $operationType;

  /**
   * The model ID for the request.
   *
   * @var string
   */
  protected $modelId;

  /**
   * The input for the request.
   *
   * @var mixed
   */
  protected $input;

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected $output;

  /**
   * The tags for the request.
   *
   * @var array
   */
  protected $tags;

  /**
   * The debug data for the request.
   *
   * @var array
   */
  protected $debugData = [];

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
   * @param string $provider_id
   *   The provider to process.
   * @param string $operation_type
   *   The operation type for the request.
   * @param array $configuration
   *   The configuration of the provider.
   * @param mixed $input
   *   The input for the request.
   * @param string $model_id
   *   The model ID for the request.
   * @param mixed $output
   *   The output for the request.
   * @param array $tags
   *   The tags for the request.
   * @param array $debug_data
   *   The debug data for the request.
   * @param array $metadata
   *   The metadata to store for the request.
   */
  public function __construct(string $request_thread_id, string $provider_id, string $operation_type, array $configuration, mixed $input, string $model_id, mixed $output, array $tags = [], array $debug_data = [], array $metadata = []) {
    $this->requestThreadId = $request_thread_id;
    $this->providerId = $provider_id;
    $this->configuration = $configuration;
    $this->operationType = $operation_type;
    $this->modelId = $model_id;
    $this->input = $input;
    $this->output = $output;
    $this->tags = $tags;
    $this->debugData = $debug_data;
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
   * Gets the provider.
   *
   * @return string
   *   The provider id.
   */
  public function getProviderId() {
    return $this->providerId;
  }

  /**
   * Gets the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Gets the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperationType() {
    return $this->operationType;
  }

  /**
   * Gets the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getModelId() {
    return $this->modelId;
  }

  /**
   * Gets the input.
   *
   * @return mixed
   *   The input.
   */
  public function getInput() {
    return $this->input;
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
   * Gets the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags() {
    return $this->tags;
  }

  /**
   * Allow to set a new tag.
   *
   * @param string $tag
   *   The tag.
   * @param mixed $value
   *   The value.
   */
  public function setTag(string $tag, mixed $value) {
    $this->tags[$tag] = $value;
  }

  /**
   * Gets the debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData() {
    return $this->debugData;
  }

  /**
   * Set extra debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugData(string $key, mixed $value) {
    $this->debugData[$key] = $value;
  }

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Sets the output.
   *
   * @param mixed $output
   *   The output.
   */
  public function setOutput(mixed $output) {
    $this->output = $output;
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
