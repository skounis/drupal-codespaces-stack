<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Test covering the PublishedStateConstraintValidator.
 *
 * @coversDefaultClass \Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint\PublishStateConstraintValidator
 *
 * @group scheduler_content_moderation_integration
 */
class PublishedStateConstraintTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Test valid publish state transitions.
   *
   * @covers ::validate
   *
   * @dataProvider dataEntityTypes
   */
  public function testValidPublishStateTransition($entityTypeId, $bundle) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'moderation_state' => 'draft',
      'publish_on' => strtotime('tomorrow'),
      'publish_state' => 'published',
    ]);
    // Assert that the publish state passes validation.
    $violations = $entity->validate();
    $this->assertCount(0, $violations);
  }

  /**
   * Test invalid publish state transitions.
   *
   * @covers ::validate
   *
   * @dataProvider dataEntityTypes
   */
  public function testInvalidPublishStateTransition($entityTypeId, $bundle) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'moderation_state' => 'draft',
      'publish_on' => strtotime('tomorrow'),
      'publish_state' => 'archived',
    ]);

    // Assert that the invalid publish state fails validation and produces our
    // specific transition constraint message. We get two violations since the
    // 'archived' value does not exist in the select list, however we should
    // only test for the violation that is produced by this module.
    $violations = $entity->validate();
    $message = (count($violations) > 0) ? $violations->get(0)->getMessage() : 'No violation message found';
    $this->assertEquals('The scheduled publishing state of Archived is not a valid transition from the current moderation state of Draft for this content.', strip_tags($message));

    // @todo Figure out how to actually test this with valid options that don't
    // break the select list widget but still test the invalid transition.
  }

}
