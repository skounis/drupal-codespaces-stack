<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Kernel;

use Drupal\node\Entity\Node;

/**
 * Test covering the UnPublishedStateConstraintValidator.
 *
 * @coversDefaultClass \Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint\UnPublishStateConstraintValidator
 *
 * @group scheduler_content_moderation_integration
 */
class UnPublishedStateConstraintTest extends SchedulerContentModerationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createMock('Drupal\Core\Session\AccountInterface');
    $user->method('hasPermission')->willReturn(TRUE);
    $this->container->set('current_user', $user);
  }

  /**
   * Test published to unpublished transition.
   *
   * Test valid scheduled publishing state to valid scheduled un-publish
   * state transitions.
   *
   * @covers ::validate
   */
  public function testValidPublishStateToUnPublishStateTransition() {
    $node = Node::create([
      'type' => 'example',
      'title' => 'Test title',
      'moderation_state' => 'draft',
      'unpublish_on' => strtotime('+3 days'),
      'publish_on' => strtotime('+2 days'),
      'unpublish_state' => 'archived',
      'publish_state' => 'published',
    ]);

    $violations = $node->validate();
    $this->assertCount(0, $violations, 'Both transitions should pass validation');
  }

  /**
   * Test an invalid un-publish transition.
   *
   * Test an invalid un-publish transition from a nodes current moderation
   * state.
   *
   * @cover ::validate
   */
  public function testInvalidUnPublishStateTransition() {
    // Check cases when a publish_state has been selected and not selected.
    // No publish_on date been entered, so they should fail validation.
    foreach (['', '_none', 'published'] as $publish_state) {
      $node = Node::create([
        'type' => 'example',
        'title' => 'Test title',
        'moderation_state' => 'draft',
        'publish_state' => $publish_state,
        'unpublish_on' => strtotime('tomorrow'),
        'unpublish_state' => 'archived',
      ]);

      // Assert that the change from draft to archived fails validation.
      $violations = $node->validate();
      $message = (count($violations) > 0) ? $violations->get(0)->getMessage() : 'No violation message found';
      $this->assertEquals('The scheduled un-publishing state of Archived is not a valid transition from the current moderation state of Draft for this content.', strip_tags($message));
    }
  }

  /**
   * Test invalid transition.
   *
   * Test invalid transition from scheduled published to scheduled un-published
   * state.
   *
   * @covers ::validate
   */
  public function testInvalidPublishStateToUnPublishStateTransition() {
    // Add a second published state, and a transition to it from draft, but no
    // transition from it to archived.
    $this->workflow->getTypePlugin()
      ->addState('published_2', 'Published 2')
      ->addTransition('published_2', 'Published 2', ['draft'], 'published_2');

    $config = $this->workflow->getTypePlugin()->getConfiguration();
    $config['states']['published_2']['published'] = TRUE;
    $config['states']['published_2']['default_revision'] = TRUE;

    $this->workflow->getTypePlugin()->setConfiguration($config);
    $this->workflow->save();

    $node = Node::create([
      'type' => 'example',
      'title' => 'Test title',
      'moderation_state' => 'draft',
      'publish_on' => strtotime('tomorrow'),
      'unpublish_on' => strtotime('+2 days'),
      'unpublish_state' => 'archived',
      'publish_state' => 'published_2',
    ]);

    // Check that the attempted scheduled transition from the new published_2
    // state to archived fails validation.
    $violations = $node->validate();
    $this->assertCount(1, $violations, 'The transition from published 2 to archived should fail validation');
    $message = (count($violations) > 0) ? $violations->get(0)->getMessage() : 'No violation message found';
    $this->assertEquals('The scheduled un-publishing state of Archived is not a valid transition from the scheduled publishing state of Published 2.', strip_tags($message));
  }

}
