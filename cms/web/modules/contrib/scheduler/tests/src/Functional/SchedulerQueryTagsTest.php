<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the Query Tag Alter hook functions for the Scheduler module.
 *
 * @group scheduler_api
 */
class SchedulerQueryTagsTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['scheduler_extras'];

  /**
   * Covers three Query Tag Alter hook functions relating to publishing.
   *
   * The following functions are implemented in test module 'scheduler_extras':
   *   hook_query_scheduler_alter()
   *   hook_query_scheduler_publish_alter()
   *   hook_query_scheduler_{entityTypeId}_publish_alter()
   *
   * The {entityTypeId} hooks are only implemented for node and media entities.
   * As the processing is identical from Scheduler's perspective there is no
   * benefit (and a certain amount of overhead) in testing on any more than two
   * entity types.
   *
   * @dataProvider dataQueryTags
   */
  public function testPublishingQueryTags($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);

    $defaults = [
      'status' => FALSE,
      'publish_on' => strtotime('-1 day'),
      'langcode' => 'dk',
    ];

    // Create test entities using the standard scheduler test entity types.
    // Entity 1 has only the default properties and will be published.
    $entity1 = $this->createEntity($entityTypeId, $bundle, $defaults);

    // Entity 2 has a date older than 12 months so hook_query_scheduler_alter()
    // will prevent it from being published.
    $entity2 = $this->createEntity($entityTypeId, $bundle, ['publish_on' => strtotime('-15 months')] + $defaults);

    // Entity 3 is in Spanish so hook_query_scheduler_publish_alter() will
    // prevent it from being published.
    $entity3 = $this->createEntity($entityTypeId, $bundle, ['langcode' => 'es'] + $defaults);

    // Entity 4 has a specific title which will cause it to be prevented from
    // publishing by hook_query_scheduler_{$entityTypeId}_publish_alter().
    $entity4 = $this->createEntity($entityTypeId, $bundle, ["$titleField" => "Do not publish this $entityTypeId"] + $defaults);

    // Before cron, check that all 4 entities are unpublished.
    for ($i = 1; $i <= 4; $i++) {
      $this->assertFalse(${"entity$i"}->isPublished(), "Before cron, $entityTypeId $i '{${"entity$i"}->label()}' should be unpublished.");
    }

    // Run cron and refresh the entities.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 1; $i <= 4; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // Entity 1 should now be published but 2 - 4 should remain unpublished.
    $this->assertTrue($entity1->isPublished(), "After first cron, $entityTypeId 1 should be published.");
    for ($i = 2; $i <= 4; $i++) {
      $this->assertFalse(${"entity$i"}->isPublished(), "After first cron, $entityTypeId $i should remain unpublished.");
    }

    // Update the fields that were preventing the entities from being published.
    $entity2->publish_on = $this->requestTime - 1;
    $entity2->save();

    $entity3->langcode = 'dk';
    $entity3->save();

    $entity4->$titleField = 'Title OK';
    $entity4->save();

    // Run cron and refresh the entities.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 2; $i <= 4; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // Entities 2 - 4 should now be published.
    for ($i = 2; $i <= 4; $i++) {
      $this->assertTrue(${"entity$i"}->isPublished(), "After second cron, $entityTypeId $i should be published.");
    }

  }

  /**
   * Covers three Query Tag Alter hook functions relating to unpublishing.
   *
   * The following functions are implemented in test module 'scheduler_extras':
   *   hook_query_scheduler_alter()
   *   hook_query_scheduler_unpublish_alter()
   *   hook_query_scheduler_{entityTypeId}_unpublish_alter()
   *
   * @dataProvider dataQueryTags
   */
  public function testUnpublishingQueryTags($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);

    $defaults = [
      'status' => TRUE,
      'unpublish_on' => strtotime('-1 day'),
      'langcode' => 'es',
    ];

    // Create test entities using the standard scheduler test entity types.
    // Entity 1 has only the default properties and will be unpublished.
    $entity1 = $this->createEntity($entityTypeId, $bundle, $defaults);

    // Entity 2 has a date older than 12 months so hook_query_scheduler_alter()
    // will prevent it from being unpublished.
    $entity2 = $this->createEntity($entityTypeId, $bundle, ['unpublish_on' => strtotime('-15 months')] + $defaults);

    // Entity 3 is in Danish so hook_query_scheduler_publish_alter() will
    // prevent it from being published.
    $entity3 = $this->createEntity($entityTypeId, $bundle, ['langcode' => 'dk'] + $defaults);

    // Entity 4 has a specific title which will cause it to be prevented from
    // publishing by hook_query_scheduler_{$entityTypeId}_publish_alter().
    $entity4 = $this->createEntity($entityTypeId, $bundle, ["$titleField" => "Do not unpublish this $entityTypeId"] + $defaults);

    // Before cron, check that all 4 entities are published.
    for ($i = 1; $i <= 4; $i++) {
      $this->assertTrue(${"entity$i"}->isPublished(), "Before cron, $entityTypeId $i '{${"entity$i"}->label()}' should be published.");
    }

    // Run cron and refresh the entities.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 1; $i <= 4; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // Entity 1 should now be unpublished but 2 - 4 should remain published.
    $this->assertFalse($entity1->isPublished(), "After first cron, $entityTypeId 1 should be unpublished.");
    for ($i = 2; $i <= 4; $i++) {
      $this->assertTrue(${"entity$i"}->isPublished(), "After first cron, $entityTypeId $i should remain published.");
    }

    // Update the fields that were preventing the entities from being published.
    $entity2->unpublish_on = $this->requestTime - 1;
    $entity2->save();

    $entity3->langcode = 'es';
    $entity3->save();

    $entity4->$titleField = 'Title OK';
    $entity4->save();

    // Run cron and refresh the entities.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 2; $i <= 4; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // Entities 2 - 4 should now be unpublished.
    for ($i = 2; $i <= 4; $i++) {
      $this->assertFalse(${"entity$i"}->isPublished(), "After second cron, $entityTypeId $i should be unpublished.");
    }

  }

  /**
   * Provides test data for QueryTags test.
   *
   * These tests are only run for Node and Media entities to save resources.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataQueryTags() {
    $data = self::dataStandardEntityTypes();
    // Remove the unrequired entity types. This caters for temporary test runs
    // where #node or #media may already be removed.
    unset($data['#commerce_product']);
    unset($data['#taxonomy_term']);
    return $data;
  }

}
