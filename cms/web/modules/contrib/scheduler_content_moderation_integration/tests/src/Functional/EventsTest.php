<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Test SchedulerEventSubscriber for reacting to Scheduler events.
 *
 * @group scheduler_content_moderation_integration
 */
class EventsTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Tests the PUBLISH_IMMEDIATELY Scheduler event subscriber.
   *
   * @dataProvider dataEntityTypes
   */
  public function testEvents($entityTypeId, $bundle): void {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);
    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';
    $storage = \Drupal::service('entity_type.manager')->getStorage($entityTypeId);

    // Set the Scheduler option to publish immediately for a date in the past.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();

    // Create a moderated entity in draft state with a scheduled publishing date
    // in the past. On saving, our event subscriber should react to the
    // PUBLISH_IMMEDIATELY event and update the entity's moderation state.
    $title = 'Hello';
    $edit = [
      "{$titleField}[0][value]" => $title,
      'moderation_state[0][state]' => 'draft',
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('-2 day')),
      'publish_on[0][value][time]' => '00:00:00',
      'publish_state[0]' => 'published',
    ];
    $this->drupalGet("{$entityTypeId}/add/{$bundle}");
    $this->submitForm($edit, 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, $title);

    // Check that the entity is immediately published and the moderation state
    // has been updated to 'published'.
    $this->assertTrue($entity->isPublished(), 'The entity is published.');
    $this->assertEquals('published', $entity->moderation_state->value, 'The entity moderation state is published');

    // Repeat the process for editing the existing entity. First reset the
    // moderation state to 'draft' and set the entity status to unpublished.
    $entity->moderation_state->value = 'draft';
    // $entity->set('status', FALSE);
    $entity->save();
    $this->assertEquals('draft', $entity->moderation_state->value, 'The entity moderation state is draft');
    $this->assertFalse($entity->isPublished(), 'The entity is unpublished.');

    // Edit the entity to set a scheduled transition to 'published' in the past.
    $edit = [
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('-1 day')),
      'publish_on[0][value][time]' => '00:00:00',
      'publish_state[0]' => 'published',
    ];
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');

    // Check that after editing, the entity is immediately published and the
    // moderation state has been updated to 'published'.
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), 'The entity is published.');
    $this->assertEquals('published', $entity->moderation_state->value, 'The entity moderation state is published');

  }

}
