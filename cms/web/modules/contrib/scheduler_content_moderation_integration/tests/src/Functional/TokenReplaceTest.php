<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Generates text using placeholders to check token replacement.
 *
 * @group scheduler_content_moderation_integration
 */
class TokenReplaceTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Creates a node, then tests the tokens generated from it.
   */
  public function testTokenReplacement() {
    // Define body text and the expected replacements.
    $body = implode('. ', [
      'Publish state = [node:scheduled-moderation-publish-state]',
      'Unpublish state = [node:scheduled-moderation-unpublish-state]',
    ]);
    $expected_output = implode('. ', [
      'Publish state = Published',
      'Unpublish state = Archived',
    ]);

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => FALSE,
      'moderation_state' => 'draft',
      'publish_on' => strtotime('+1 hour'),
      'publish_state' => 'published',
      'unpublish_on' => strtotime('+2 hour'),
      'unpublish_state' => 'archived',
      'body' => $body,
    ]);
    $body_output = \Drupal::token()->replace($node->body->value, ['node' => $node]);
    $this->assertEquals($expected_output, $body_output, 'Tokens replaced correctly');

  }

}
