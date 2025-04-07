<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Entity\EntityInterface;

/**
 * Tests revision options when Scheduler publishes or unpublishes content.
 *
 * @group scheduler
 */
class SchedulerRevisioningTest extends SchedulerBrowserTestBase {

  /**
   * Simulates the scheduled (un)publication of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to schedule.
   * @param string $action
   *   The action to perform: either 'publish' or 'unpublish'.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The updated entity, after scheduled (un)publication via a cron run.
   */
  protected function scheduleAndRunCron(EntityInterface $entity, string $action) {
    // Simulate scheduling by setting the (un)publication date in the past and
    // running cron.
    $entity->{$action . '_on'} = strtotime('-5 hour', $this->requestTime);
    $entity->save();
    scheduler_cron();
    $storage = $this->entityStorageObject($entity->getEntityTypeId());
    $storage->resetCache([$entity->id()]);
    return $storage->load($entity->id());
  }

  /**
   * Check if the number of revisions for an entity matches a given value.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param int $expected
   *   The expected number of revisions.
   * @param string $message
   *   The message to display along with the assertion.
   */
  protected function assertRevisionCount(EntityInterface $entity, int $expected, string $message = '') {
    if (!$entity->getEntityType()->isRevisionable()) {
      return;
    }
    // Because we are not deleting any revisions we can take a short cut and use
    // getLatestRevisionId() which will effectively be the number of revisions.
    $storage = $this->entityStorageObject($entity->getEntityTypeId());
    $count = $storage->getLatestRevisionId($entity->id());
    $this->assertEquals($expected, (int) $count, $message);
  }

  /**
   * Tests the creation of new revisions on scheduling.
   *
   * This test is still useful for Commerce Products which are not revisionable
   * because it shows that this entity type can be processed correctly even if
   * the scheduler revision option is incorrectly set on.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testNewRevision($entityTypeId, $bundle) {
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    // Create a scheduled entity that is not automatically revisioned.
    $entity = $this->createEntity($entityTypeId, $bundle, ['revision' => 0]);
    $this->assertRevisionCount($entity, 1, 'The initial revision count is 1 when the entity is created.');

    // Ensure entities with past dates are scheduled not published immediately.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // First test scheduled publication with revisioning disabled by default.
    $entity = $this->scheduleAndRunCron($entity, 'publish');
    $this->assertRevisionCount($entity, 1, 'No new revision is created by default when entity is published. Revision count remains at 1.');

    // Test scheduled unpublication.
    $entity = $this->scheduleAndRunCron($entity, 'unpublish');
    $this->assertRevisionCount($entity, 1, 'No new revision is created by default when entity is unpublished. Revision count remains at 1.');

    // Enable revisioning.
    $entityType->setThirdPartySetting('scheduler', 'publish_revision', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_revision', TRUE)
      ->save();

    // Test scheduled publication with revisioning enabled.
    $entity = $this->scheduleAndRunCron($entity, 'publish');
    $this->assertTrue($entity->isPublished(), 'Entity is published after cron.');

    if ($entity->getEntityType()->isRevisionable()) {
      $this->assertRevisionCount($entity, 2, 'A new revision was created when the entity was published with revisioning enabled.');
      $expected_message = sprintf('Published by Scheduler. The scheduled publishing date was %s.',
        $this->dateFormatter->format(strtotime('-5 hour', $this->requestTime), 'short'));
      $this->assertEquals($entity->getRevisionLogMessage(), $expected_message, 'The correct message was found in the entity revision log after scheduled publishing.');
    }

    // Test scheduled unpublication with revisioning enabled.
    $entity = $this->scheduleAndRunCron($entity, 'unpublish');
    $this->assertFalse($entity->isPublished(), 'Entity is unpublished after cron.');

    if ($entity->getEntityType()->isRevisionable()) {
      $this->assertRevisionCount($entity, 3, 'A new revision was created when the entity was unpublished with revisioning enabled.');
      $expected_message = sprintf('Unpublished by Scheduler. The scheduled unpublishing date was %s.',
        $this->dateFormatter->format(strtotime('-5 hour', $this->requestTime), 'short'));
      $this->assertEquals($entity->getRevisionLogMessage(), $expected_message, 'The correct message was found in the entity revision log after scheduled unpublishing.');
    }
  }

  /**
   * Tests the 'touch' option to alter the created date during publishing.
   *
   * @dataProvider dataAlterCreationDate
   */
  public function testAlterCreationDate($entityTypeId, $bundle) {
    // Ensure entities with past dates are scheduled not published immediately.
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Create an entity with a 'created' date two days in the past.
    $created = strtotime('-2 day', $this->requestTime);
    $settings = [
      'created' => $created,
      'status' => FALSE,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $settings);

    // Show that the entity is not published.
    $this->assertFalse($entity->isPublished(), 'The entity is not published.');

    // Schedule the entity for publishing and run cron.
    $entity = $this->scheduleAndRunCron($entity, 'publish');
    // Get the created date from the entity and check that it has not changed.
    $created_after_cron = $entity->created->value;
    $this->assertTrue($entity->isPublished(), 'The entity has been published.');
    $this->assertEquals($created, $created_after_cron, 'The entity creation date is not changed by default.');

    // Set option to change the created date to match the publish_on date.
    $entityType->setThirdPartySetting('scheduler', 'publish_touch', TRUE)->save();

    // Schedule the entity again and run cron.
    $entity = $this->scheduleAndRunCron($entity, 'publish');
    // Check that the created date has changed to match the publish_on date.
    $created_after_cron = $entity->created->value;
    $this->assertEquals(strtotime('-5 hour', $this->requestTime), $created_after_cron, "With 'touch' option set, the entity creation date is changed to match the publishing date.");

  }

  /**
   * Provides test data for testAlterCreationDate.
   *
   * Taxonomy terms do not have a 'created' date and the therefore the 'touch'
   * option is not available, and the test should be skipped.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataAlterCreationDate() {
    $data = self::dataStandardEntityTypes();
    unset($data['#taxonomy_term']);
    return $data;
  }

}
