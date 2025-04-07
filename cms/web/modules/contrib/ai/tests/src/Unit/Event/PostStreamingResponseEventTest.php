<?php

namespace Drupal\Tests\ai\Unit\Event;

use Drupal\ai\Event\PostStreamingResponseEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the event function works.
 *
 * @group ai
 * @covers \Drupal\ai\Event\PostStreamingResponseEvent
 */
class PostStreamingResponseEventTest extends TestCase {

  /**
   * Test get event id.
   */
  public function testEventId(): void {
    $event = $this->getEvent();
    $this->assertEquals('unique_id', $event->getRequestThreadId());
  }

  /**
   * Test get response.
   */
  public function testResponse(): void {
    $event = $this->getEvent();
    $this->assertEquals('test', $event->getOutput());
  }

  /**
   * Helper function to get the events.
   *
   * @return \Drupal\ai\Event\PostStreamingResponseEvent|\PHPUnit\Framework\MockObject\MockObject
   *   The event.
   */
  public function getEvent(): PostStreamingResponseEvent {
    return new PostStreamingResponseEvent('unique_id', 'test', [
      'test' => 'test',
    ]);
  }

}
