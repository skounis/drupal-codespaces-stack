<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the modules primary functions with a Taxonomy Term entity type.
 *
 * @group scheduler
 */
class SchedulerBasicTaxonomyTermTest extends SchedulerBrowserTestBase {

  /**
   * Tests scheduled publishing of a taxonomy term entity.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::publish.
   */
  public function testTaxonomyTermPublishing() {
    // Specify values for the entity.
    $values = [
      'name' => 'Publish This Taxonomy Term',
      'publish_on' => $this->requestTime + 3600,
    ];
    // Create a taxonomy term with the scheduler fields populated as required.
    $entity = $this->createTaxonomyTerm($values);
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
    $this->taxonomyTermStorage->resetCache([$entity->id()]);
    $entity = $this->taxonomyTermStorage->load($entity->id());
    $this->assertTrue($entity->isPublished(), 'The entity is published after cron run');
  }

  /**
   * Tests scheduled publishing of a taxonomy term when action is missing.
   */
  public function testMissingActionTaxonomyTermPublishing() {
    $this->deleteAction('taxonomy_term_scheduler', 'publish');
    $this->testTaxonomyTermPublishing();
  }

  /**
   * Tests scheduled unpublishing of a taxonomy term.
   *
   * Covers scheduler_entity_presave(), scheduler_cron(),
   * schedulerManager::unpublish.
   */
  public function testTaxonomyTermUnpublishing() {
    // Specify values for the entity.
    $values = [
      'name' => 'Unpublish This Taxonomy Term',
      'unpublish_on' => $this->requestTime + 3600,
    ];
    // Create a taxonomy term with the scheduler fields populated as required.
    $entity = $this->createTaxonomyTerm($values);
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
    $this->taxonomyTermStorage->resetCache([$entity->id()]);
    $entity = $this->taxonomyTermStorage->load($entity->id());
    $this->assertFalse($entity->isPublished(), 'The entity is unpublished after cron run');

  }

  /**
   * Tests scheduled unpublishing of a taxonomy term when action is missing.
   */
  public function testMissingActionTaxonomyTermUnpublishing() {
    $this->deleteAction('taxonomy_term_scheduler', 'unpublish');
    $this->testTaxonomyTermUnpublishing();
  }

}
