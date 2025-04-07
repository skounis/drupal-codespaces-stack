<?php

namespace Drupal\eca\Event;

use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatches when a token is about to be generated.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca\Event
 */
class TokenGenerateEvent extends Event {

  /**
   * The machine-readable name of the type (group) of token being replaced.
   *
   * @var string
   */
  protected string $type;

  /**
   * The token name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The original token name.
   *
   * @var string
   */
  protected string $original;

  /**
   * An associative array of data objects to be used.
   *
   * @var array
   */
  protected array $data;

  /**
   * An associative array of options for token replacement.
   *
   * @var array
   */
  protected array $options;

  /**
   * The bubbleable metadata.
   *
   * @var \Drupal\Core\Render\BubbleableMetadata
   */
  protected BubbleableMetadata $bubbleableMetadata;

  /**
   * Constructs a token event.
   *
   * @param string $type
   *   The machine-readable name of the type (group) of token being replaced.
   * @param string $name
   *   The token name.
   * @param string $original
   *   The original token name.
   * @param array $data
   *   An associative array of data objects to be used.
   * @param array $options
   *   An associative array of options for token replacement.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata.
   */
  public function __construct(string $type, string $name, string $original, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $this->type = $type;
    $this->name = $name;
    $this->original = $original;
    $this->setData($data);
    $this->options = $options;
    $this->bubbleableMetadata = $bubbleable_metadata;
  }

  /**
   * Get the machine-readable name of the type (group) of token being replaced.
   *
   * @return string
   *   The machine-readable name of the type (group) of token being replaced.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Get the token name.
   *
   * @return string
   *   The token name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the original token name.
   *
   * @return string
   *   The original token name.
   */
  public function getOriginal(): string {
    return $this->original;
  }

  /**
   * Get an associative array of data objects to be used.
   *
   * @return array
   *   An associative array of data objects to be used.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Set an associative array of data objects to be used.
   *
   * @param array $data
   *   The data to be used.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Get an associative array of options for token replacement.
   *
   * @return array
   *   An associative array of options for token replacement.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Get the bubbleable metadata.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   The bubbleable metadata.
   */
  public function getBubbleableMetadata(): BubbleableMetadata {
    return $this->bubbleableMetadata;
  }

}
