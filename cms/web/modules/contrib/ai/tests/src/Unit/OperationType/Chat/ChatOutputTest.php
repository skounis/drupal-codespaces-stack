<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\ChatInput
 */
class ChatOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('assistant', $output->getNormalized()->getRole());
    $this->assertEquals('That is great!', $output->getNormalized()->getText());
    $this->assertEquals([], $output->getMetadata());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Chat\ChatInput
   *   The output.
   */
  public function getOutput(): ChatOutput {
    $output = new ChatMessage('assistant', 'That is great!');
    return new ChatOutput($output, [
      'role' => 'assistant',
      'message' => 'That is great!',
    ], []);
  }

}
