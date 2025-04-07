<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\Chat;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * This tests the Chat calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\Chat\ChatInterface
 *
 * @group ai
 */
class ChatInterfaceTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * Setup the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', [
      'file_usage',
    ]);
  }

  /**
   * Test the chat service with mockup OpenAI Provider.
   */
  public function testChatNormalized(): void {
    $text = 'Can you help me with something?';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', $text),
    ]);
    $chat_response = $provider->chat($input, 'test');
    // Should be a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $chat_response);
    // Should have a message.
    $message = $chat_response->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $message);

    // Response should be a string and be the following.
    $response_text = "Hello world! Input: $text. Config: [].";
    $this->assertIsString($message->getText());
    $this->assertEquals($response_text, $message->getText());
  }

  /**
   * Test that the streaming chat works.
   */
  public function testChatStream(): void {
    $text = 'Can you help me with something?';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', $text),
    ]);
    // Set to streaming.
    $provider->streamedOutput(TRUE);
    $chat_response = $provider->chat($input, 'test');
    // Should be a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $chat_response);
    // Should have a streaming response.
    $message = $chat_response->getNormalized();
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $message);

    // Response should be a string and be the following.
    $response_text = "Hello world! Input: $text. Config: [].";
    // Its an iterator.
    foreach ($message as $message_part) {
      $this->assertIsString($message_part->getText());
      $this->assertEquals($response_text, trim($message_part->getText(), "\n"));
    }
  }

  /**
   * Test some errors.
   */
  public function testErrors(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    // Empty input.
    $input = new ChatInput([
      new ChatMessage('', ''),
    ]);
    // This should throw an error because lacking input.
    $this->expectException(AiRequestErrorException::class);
    $provider->chat($input, 'test');

    // Working input.
    $input = new ChatInput([
      new ChatMessage('user', 'hello there'),
    ]);
    // This should throw an error because lacking model.
    $this->expectException(AiBadRequestException::class);
    $provider->chat($input);
  }

}
