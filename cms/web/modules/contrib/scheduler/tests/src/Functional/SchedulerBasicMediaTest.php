<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the modules primary functions with a Media entity type.
 *
 * @group scheduler
 */
class SchedulerBasicMediaTest extends SchedulerBrowserTestBase {

  /**
   * Tests scheduled publishing of a media entity.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::publish.
   */
  public function testMediaPublishing() {
    // Specify values for the entity.
    $values = [
      'name' => 'Publish This Media',
      'publish_on' => $this->requestTime + 3600,
    ];
    // Create a media entity with the scheduler fields populated as required.
    $entity = $this->createMediaItem($values);
    $this->assertNotEmpty($entity, 'The entity was created successfully.');

    // Assert that the entity has a publish_on date.
    $this->assertNotEmpty($entity->publish_on, 'The entity has a publish_on date');

    // Assert that the entity is not published before cron.
    $this->assertFalse($entity->isPublished(), 'The entity is unpublished before cron run');

    // Modify the scheduler field to a time in the past, then run cron.
    $entity->publish_on = $this->requestTime - 1;
    $entity->save();
    $this->cronRun();

    // Refresh the cache, reload the entity and check the entity is published.
    $this->mediaStorage->resetCache([$entity->id()]);
    $entity = $this->mediaStorage->load($entity->id());
    $this->assertTrue($entity->isPublished(), 'The entity is published after cron run');
  }

  /**
   * Tests scheduled publishing of a media entity when action is missing.
   */
  public function testMissingActionMediaPublishing() {
    $this->deleteAction('media_scheduler', 'publish');
    $this->testMediaPublishing();
  }

  /**
   * Tests scheduled unpublishing of a media entity.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::unpublish.
   */
  public function testMediaUnpublishing() {
    // Specify values for the entity.
    $values = [
      'name' => 'Unpublish This Media',
      'unpublish_on' => $this->requestTime + 3600,
    ];
    // Create a media entity with the scheduler fields populated as required.
    $entity = $this->createMediaItem($values);
    $this->assertNotEmpty($entity, 'The entity was created successfully.');

    // Assert that the entity has an unpublish_on date.
    $this->assertNotEmpty($entity->unpublish_on, 'The entity has an unpublish_on date');

    // Assert that the entity is published before cron.
    $this->assertTrue($entity->isPublished(), 'The entity is published before cron run');

    // Modify the scheduler field to a time in the past, then run cron.
    $entity->unpublish_on = $this->requestTime - 1;
    $entity->save();
    $this->cronRun();

    // Refresh the cache, reload the entity and check the entity is unpublished.
    $this->mediaStorage->resetCache([$entity->id()]);
    $entity = $this->mediaStorage->load($entity->id());
    $this->assertFalse($entity->isPublished(), 'The entity is unpublished after cron run');

  }

  /**
   * Tests scheduled unpublishing of a media entity when action is missing.
   */
  public function testMissingActionMediaUnpublishing() {
    $this->deleteAction('media_scheduler', 'unpublish');
    $this->testMediaUnpublishing();
  }

}
