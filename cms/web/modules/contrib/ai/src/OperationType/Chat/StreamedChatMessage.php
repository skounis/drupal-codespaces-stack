<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Streamed chat message.
 */
class StreamedChatMessage implements StreamedChatMessageInterface {

  /**
   * The role.
   *
   * @var string
   */
  private string $role;

  /**
   * The text.
   *
   * @var string
   */
  private string $text;

  /**
   * The metadata.
   *
   * @var array
   */
  private array $metadata;

  /**
   * Constructor.
   */
  public function __construct(string $role = "", string $text = "", array $metadata = []) {
    $this->role = $role;
    $this->text = $text;
    $this->metadata = $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * {@inheritdoc}
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * {@inheritdoc}
   */
  public function getRole(): string {
    return $this->role;
  }

  /**
   * {@inheritdoc}
   */
  public function setRole(string $role): void {
    $this->role = $role;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

}
