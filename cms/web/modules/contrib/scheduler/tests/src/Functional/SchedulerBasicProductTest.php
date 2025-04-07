<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the modules primary functions with a Commerce Product entity type.
 *
 * @group scheduler
 */
class SchedulerBasicProductTest extends SchedulerBrowserTestBase {

  /**
   * Tests scheduled publishing of a commerce product entity.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::publish.
   */
  public function testProductPublishing() {
    // Specify values for the entity.
    $values = [
      'title' => 'Publish This Product',
      'publish_on' => $this->requestTime + 3600,
    ];
    // Create a product entity with the scheduler fields populated as required.
    $entity = $this->createProduct($values);
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
    $this->productStorage->resetCache([$entity->id()]);
    $entity = $this->productStorage->load($entity->id());
    $this->assertTrue($entity->isPublished(), 'The entity is published after cron run');
  }

  /**
   * Tests scheduled publishing of a product when action is missing.
   */
  public function testMissingActionProductPublishing() {
    $this->deleteAction('commerce_product_scheduler', 'publish');
    $this->testProductPublishing();
  }

  /**
   * Tests scheduled unpublishing of a commerce product entity.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::unpublish.
   */
  public function testProductUnpublishing() {
    // Specify values for the entity.
    $values = [
      'title' => 'Unpublish This Product',
      'unpublish_on' => $this->requestTime + 3600,
    ];
    // Create a product with the scheduler fields populated as required.
    $entity = $this->createProduct($values);
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
    $this->productStorage->resetCache([$entity->id()]);
    $entity = $this->productStorage->load($entity->id());
    $this->assertFalse($entity->isPublished(), 'The entity is unpublished after cron run');

  }

  /**
   * Tests scheduled unpublishing of a product when action is missing.
   */
  public function testMissingActionProductUnpublishing() {
    $this->deleteAction('commerce_product_scheduler', 'unpublish');
    $this->testProductUnpublishing();
  }

}
