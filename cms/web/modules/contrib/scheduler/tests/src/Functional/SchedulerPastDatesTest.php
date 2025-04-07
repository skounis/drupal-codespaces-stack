<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the options and processing when dates are entered in the past.
 *
 * @group scheduler
 */
class SchedulerPastDatesTest extends SchedulerBrowserTestBase {

  /**
   * Test the different options for past publication dates.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testSchedulerPastDates($entityTypeId, $bundle) {
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    // For taxonomy, log in as adminUser to avoid 403 for unpublished terms.
    $entityTypeId == 'taxonomy_term' ? $this->drupalLogin($this->adminUser) : $this->drupalLogin($this->schedulerUser);

    // Create data for use in edits.
    $title = 'Publish in the past ' . $this->randomString(10);
    $edit = [
      "{$titleField}[0][value]" => $title,
      'publish_on[0][value][date]' => $this->dateFormatter->format(strtotime('-1 day', $this->requestTime), 'custom', 'Y-m-d'),
      'publish_on[0][value][time]' => $this->dateFormatter->format(strtotime('-1 day', $this->requestTime), 'custom', 'H:i:s'),
    ];

    // Create an unpublished entity.
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);
    // Some entities do not have a 'created' date and if that is the case we
    // skip the specific parts of this test that relate to this.
    if ($check_created_time = method_exists($entity, 'getCreatedTime')) {
      $created_time = $entity->getCreatedTime();
    }

    // Test the default behavior: an error message should be shown when the user
    // enters a publication date that is in the past.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("The 'publish on' date must be in the future");

    // Test the 'error' behavior explicitly.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'error')->save();
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("The 'publish on' date must be in the future");

    // Test the 'publish' behavior: the entity should be published immediately.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check that no error message is shown when the publication date is in the
    // past and the "publish" behavior is chosen.
    $this->assertSession()->pageTextNotContains("The 'publish on' date must be in the future");
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());

    // Check that the entity is published and has the expected timestamps.
    $this->assertTrue($entity->isPublished(), 'The entity has been published immediately when the publication date is in the past and the "publish" behavior is chosen.');
    $this->assertNull($entity->publish_on->value, 'The entity publish_on date has been removed after publishing when the "publish" behavior is chosen.');
    $this->assertEquals($entity->getChangedTime(), strtotime('-1 day', $this->requestTime), 'The changed time of the entity has been updated to the publish_on time when published immediately.');
    $check_created_time ? $this->assertEquals($entity->getCreatedTime(), $created_time, 'The created time of the entity has not been changed when the "publish" behavior is chosen.') : NULL;

    // Test the 'schedule' behavior: the entity should be unpublished and become
    // published on the next cron run. Use a new unpublished entity.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);
    $check_created_time ? $created_time = $entity->getCreatedTime() : NULL;

    // Edit, save and check that no error is shown when the publish_on date is
    // in the past.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains("The 'publish on' date must be in the future");
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published', $title));
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());

    // Check that the entity is unpublished but scheduled correctly.
    $this->assertFalse($entity->isPublished(), 'The entity has been unpublished when the publication date is in the past and the "schedule" behavior is chosen.');
    $this->assertEquals(strtotime('-1 day', $this->requestTime), (int) $entity->publish_on->value, 'The entity has the correct publish_on date stored.');

    // Simulate a cron run and check that the entity is published.
    scheduler_cron();
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), 'The entity with publication date in the past and the "schedule" behavior has now been published by cron.');
    $this->assertEquals($entity->getChangedTime(), strtotime('-1 day', $this->requestTime), 'The changed time of the entity has been updated to the publish_on time when published via cron.');
    $check_created_time ? $this->assertEquals($entity->getCreatedTime(), $created_time, 'The created time of the entity has not been changed when the "schedule" behavior is chosen.') : NULL;

    // Test the option to alter the creation time if the publishing time is
    // earlier than the entity created time.
    if ($check_created_time) {
      $entityType->setThirdPartySetting('scheduler', 'publish_past_date_created', TRUE)->save();
      $past_date_options = [
        'publish' => 'publish',
        'schedule' => 'schedule',
      ];
      foreach ($past_date_options as $key => $option) {
        $entityType->setThirdPartySetting('scheduler', 'publish_past_date', $key)->save();

        // Create a new unpublished entity, edit and save.
        $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);
        $this->drupalGet($entity->toUrl('edit-form'));
        $this->submitForm($edit, 'Save');

        if ($option == 'schedule') {
          scheduler_cron();
        }

        // Reload the entity.
        $storage->resetCache([$entity->id()]);
        $entity = $storage->load($entity->id());

        // Check that the created time is altered to match publishing time.
        $this->assertEquals($entity->getCreatedTime(), strtotime('-1 day', $this->requestTime), sprintf('The created time of the entity has not been changed when the %s option is chosen.', $option));
      }
    }

    // Check that an Unpublish date in the past fails validation.
    $edit = [
      "{$titleField}[0][value]" => 'Unpublish in the past ' . $this->randomString(10),
      'unpublish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime - 3600, 'custom', 'Y-m-d'),
      'unpublish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime - 3600, 'custom', 'H:i:s'),
    ];
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("The 'unpublish on' date must be in the future");
  }

}
