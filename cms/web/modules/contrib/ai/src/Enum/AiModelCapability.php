<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * Enum of AI provider model capabilities, which aren't shared across.
 */
enum AiModelCapability: string {
  case ChatWithImageVision = 'chat_with_image_vision';
  case ChatWithAudio = 'chat_with_audio';
  case ChatWithVideo = 'chat_with_video';
  case ChatSystemRole = 'chat_system_role';
  case ChatJsonOutput = 'chat_json_output';

  /**
   * Get a base operation type for the capability.
   *
   * @return string
   *   The operation type.
   */
  public function getBaseOperationType(): string {
    return match ($this) {
      self::ChatWithImageVision, self::ChatWithAudio, self::ChatWithVideo, self::ChatSystemRole, self::ChatJsonOutput => 'chat',
    };
  }

  /**
   * Get a title for the capability.
   *
   * @return string
   *   The title.
   */
  public function getTitle(): string {
    return match ($this) {
      self::ChatWithImageVision => 'Chat with Image Vision',
      self::ChatWithAudio => 'Chat with Audio',
      self::ChatWithVideo => 'Chat with Video',
      self::ChatSystemRole => 'Chat System Role',
      self::ChatJsonOutput => 'Chat JSON Output',
    };
  }

  /**
   * Get a description for the capability.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return match ($this) {
      self::ChatWithImageVision => 'Is set if the chat model can include images in the chat input.',
      self::ChatWithAudio => 'Is set if the chat model can include audio in the chat input.',
      self::ChatWithVideo => 'Is set if the chat model can include video in the chat input.',
      self::ChatSystemRole => 'Is set if the chat model can include a system role.',
      self::ChatJsonOutput => 'Is set if the chat model can do flawless complex JSON output.',
    };
  }

}
