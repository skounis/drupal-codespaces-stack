<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests moderation state remains the same on entity save.
 *
 * For entities meant to be published in the future.
 *
 * @group scheduler_content_moderation_integration
 */
class RestrictPublishedModerationStateTest extends SchedulerContentModerationTestBase {

  /**
   * Node entity.
   */
  private NodeInterface $node;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->node = Node::create([
      'type' => 'example',
      'title' => 'Test title',
      'moderation_state' => 'draft',
      'publish_on' => strtotime('tomorrow'),
      'publish_state' => 'published',
    ]);
    $this->node->save();
  }

  /**
   * Tests that moderation state is not set to published.
   *
   * When entity's status is 0 and publish_on is in the future.
   */
  public function testModerationStateRemainsDraftOnEntityResave(): void {
    $this->node->set('moderation_state', 'published')->save();
    self::assertFalse($this->node->isPublished());
    self::assertEquals('draft', $this->node->get('moderation_state')->value);
  }

}
