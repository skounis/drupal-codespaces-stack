<?php

namespace Drupal\Tests\ai\Mock;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Mock chat iterator for testing.
 */
class MockStreamedChatIterator extends StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  /**
   * Get the iterator.
   *
   * @return \Traversable
   *   The iterator.
   */
  public function getIterator(): \Traversable {
    foreach ($this->iterator as $data) {
      yield new StreamedChatMessage(
        'assistant',
        $data . "\n",
        []
      );
    }
  }

}
