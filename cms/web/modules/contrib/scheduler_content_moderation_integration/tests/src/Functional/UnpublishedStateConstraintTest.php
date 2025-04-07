<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Test covering the UnPublishedStateConstraintValidator.
 *
 * @coversDefaultClass \Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint\UnPublishStateConstraintValidator
 *
 * @group scheduler_content_moderation_integration
 */
class UnpublishedStateConstraintTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Test published to unpublished transition.
   *
   * Test valid scheduled publishing state to valid scheduled un-publish
   * state transitions.
   *
   * @covers ::validate
   *
   * @dataProvider dataEntityTypes
   */
  public function testValidPublishStateToUnPublishStateTransition($entityTypeId, $bundle) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'moderation_state' => 'draft',
      'publish_on' => strtotime('+2 days'),
      'unpublish_on' => strtotime('+3 days'),
      'publish_state' => 'published',
      'unpublish_state' => 'archived',
    ]);
    // Assert that the publish and unpublish states pass validation.
    $violations = $entity->validate();
    $this->assertCount(0, $violations, 'Both transitions should pass validation');
  }

  /**
   * Test an invalid un-publish transition.
   *
   * Test an invalid un-publish transition from current moderation state of
   * draft to archived state.
   *
   * @cover ::validate
   *
   * @dataProvider dataEntityTypes
   */
  public function testInvalidUnPublishStateTransition($entityTypeId, $bundle) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);

    // Check cases when a publish_state has been selected and not selected.
    // No publish_on date been entered, so they should fail validation.
    foreach (['', '_none', 'published'] as $publish_state) {
      $entity = $this->createEntity($entityTypeId, $bundle, [
        'moderation_state' => 'draft',
        'publish_state' => $publish_state,
        'unpublish_on' => strtotime('+3 days'),
        'unpublish_state' => 'archived',
      ]);
      // Assert that the change from draft to archived fails validation.
      $violations = $entity->validate();
      $this->assertCount(1, $violations, "The transition from draft to archived with publish_state='{$publish_state}' should fail validation");
      $message = (count($violations) > 0) ? $violations->get(0)->getMessage() : 'No violation message found';
      $this->assertEquals('The scheduled un-publishing state of Archived is not a valid transition from the current moderation state of Draft for this content.', strip_tags($message));
    }
  }

  /**
   * Test invalid transition.
   *
   * Test invalid transition from scheduled publish to scheduled un-publish
   * state.
   *
   * @covers ::validate
   *
   * @dataProvider dataEntityTypes
   */
  public function testInvalidPublishStateToUnPublishStateTransition($entityTypeId, $bundle) {
    // This test is not about permissions, therefore we can use the root user
    // id 1 which will have permission to use the new state created below.
    $this->drupalLogin($this->rootUser);

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

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'moderation_state' => 'draft',
      'publish_on' => strtotime('+1 day'),
      'publish_state' => 'published_2',
      'unpublish_on' => strtotime('+2 days'),
      'unpublish_state' => 'archived',
    ]);
    // Check that the attempted scheduled transition from the new published_2
    // state to archived fails validation.
    $violations = $entity->validate();
    $this->assertCount(1, $violations, 'The transition from published 2 to archived should fail validation');
    $message = (count($violations) > 0) ? $violations->get(0)->getMessage() : 'No violation message found';
    $this->assertEquals('The scheduled un-publishing state of Archived is not a valid transition from the scheduled publishing state of Published 2.', strip_tags($message));
  }

}
