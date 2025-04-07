<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service\PromptJsonDecoder;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ai\Mock\MockIterator;
use Drupal\Tests\ai\Mock\MockStreamedChatIterator;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder
 * @group ai
 */
class PromptJsonDecoderTest extends UnitTestCase {

  /**
   * Tests messages with or without JSON in them.
   *
   * @param string $message
   *   The message to test.
   * @param int $placements
   *   The number of placements.
   * @param bool $json_exist
   *   If json exist.
   *
   * @dataProvider messageProvider
   *
   * @return void
   *   Nothing.
   */
  public function testJsonMessage(string $message, int $placements, bool $json_exist): void {
    $prompt_json_decoder = new PromptJsonDecoder();
    // First test as a normal message.
    $chat_message = new ChatMessage('assistant', $message, []);
    $decoded = $prompt_json_decoder->decode($chat_message);
    if ($json_exist) {
      $this->assertIsArray($decoded);
    }
    else {
      $this->assertInstanceOf(ChatMessage::class, $decoded);
    }

    // Now test as a streaming message.
    $iterator = new MockIterator(explode("\n", $message));
    $chat_message = new MockStreamedChatIterator($iterator);
    $decoded = $prompt_json_decoder->decode($chat_message, $placements);

    if ($json_exist) {
      $this->assertIsArray($decoded);
    }
    else {
      $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $decoded);
    }

    // If the placement is over 10, we try 5 steps earlier to see if it fails.
    if ($placements > 10) {
      $iterator = new MockIterator(explode("\n", $message));
      $chat_message = new MockStreamedChatIterator($iterator);
      $decoded = $prompt_json_decoder->decode($chat_message, ($placements - 5));
      $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $decoded);
    }
  }

  /**
   * Provides chat messages, expected token where json starts and if json exist.
   *
   * @return array
   *   Message, token placement and if json exist.
   */
  public static function messageProvider(): array {
    $messages = [];
    $dir = __DIR__ . '/../../../../assets/test-prompts/prompt-json-decoder/';
    foreach (scandir($dir) as $file) {
      if (is_file($dir . $file)) {
        $messages[] = array_values(Yaml::parseFile($dir . $file));
      }
    }
    return $messages;
  }

}
