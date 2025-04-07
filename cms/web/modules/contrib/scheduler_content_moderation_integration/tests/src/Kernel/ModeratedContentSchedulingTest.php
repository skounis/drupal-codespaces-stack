<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Kernel;

/**
 * Tests publishing/unpublishing scheduling for moderated entities.
 *
 * @group scheduler_content_moderation_integration
 */
class ModeratedContentSchedulingTest extends SchedulerContentModerationTestBase {

  /**
   * Tests moderated entity publish scheduling.
   *
   * @dataProvider dataEntityTypes
   */
  public function testPublishStateSchedule($entityTypeId, $bundle) {
    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::service('entity_type.manager')->getStorage($entityTypeId);

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => 'Published title',
      'moderation_state' => 'draft',
      'publish_on' => strtotime('yesterday'),
      'publish_state' => 'published',
    ]);
    $entity_id = $entity->id();

    // Make sure entity is unpublished.
    $this->assertFalse($entity->isPublished());
    $this->assertEquals(1, $entity->getRevisionId(), 'The initial revision id should be 1');

    $this->container->get('cron')->run();

    $entity = $storage->loadRevision($storage->getLatestRevisionId($entity_id));

    // Assert entity is now published.
    $this->assertTrue($entity->isPublished());
    $this->assertEquals('published', $entity->moderation_state->value);

    // Assert only one revision is created during the operation.
    $this->assertEquals(2, $entity->getRevisionId(), 'After publishing, the revision id should be 2');

    $entity->set($titleField, 'Draft title');
    $entity->moderation_state->value = 'draft';
    $entity->publish_on->value = strtotime('yesterday');
    $entity->publish_state->value = 'published';
    $entity->save();

    $entity = $storage->loadRevision($storage->getLatestRevisionId($entity_id));
    $this->assertEquals('Draft title', $entity->label());
    $this->assertEquals('draft', $entity->moderation_state->value);

    $this->container->get('cron')->run();

    // Assert that the entity is published after cron.
    $entity = $storage->loadRevision($storage->getLatestRevisionId($entity_id));
    $this->assertTrue($entity->isPublished());
    $this->assertEquals('published', $entity->moderation_state->value);
    $this->assertEquals('Draft title', $entity->label());
  }

  /**
   * Tests moderated entity unpublish scheduling.
   *
   * @dataProvider dataEntityTypes
   */
  public function testUnpublishStateSchedule($entityTypeId, $bundle) {
    $storage = \Drupal::service('entity_type.manager')->getStorage($entityTypeId);

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => 'Published title',
      'moderation_state' => 'published',
      'unpublish_on' => strtotime('yesterday'),
      'unpublish_state' => 'archived',
    ]);
    $entity_id = $entity->id();

    // Make sure the entity is published.
    $this->assertTrue($storage->load($entity_id)->isPublished(), 'The entity is published.');
    $this->assertEquals(1, $entity->getRevisionId(), 'The initial revision id should be 1');

    $this->container->get('cron')->run();

    // Assert entity is now unpublished.
    $this->assertFalse($storage->load($entity_id)->isPublished(), 'The entity is unpublished after cron.');

    // Assert only one revision is created during the operation.
    $this->assertEquals(2, $storage->load($entity_id)->getRevisionId(), 'After unpublishing, the revision id should be 2');
  }

  /**
   * Tests publish scheduling for a draft of a published entity.
   *
   * @dataProvider dataEntityTypes
   */
  public function testPublishOfDraft($entityTypeId, $bundle) {
    $storage = \Drupal::service('entity_type.manager')->getStorage($entityTypeId);

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => 'Published title',
      'moderation_state' => 'published',
    ]);
    $entity_id = $entity->id();

    // Assert entity is published.
    $this->assertEquals('Published title', $storage->load($entity_id)->label());
    $this->assertTrue($storage->load($entity_id)->isPublished(), 'Entity is initially published');
    $this->assertEquals(1, $entity->getRevisionId(), 'The initial revision id should be 1');

    // Create a new pending revision and validate it's not the default published
    // one.
    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';
    $entity->set($titleField, 'Draft title');
    $entity->set('publish_on', strtotime('yesterday'));
    $entity->set('moderation_state', 'draft');
    $entity->set('publish_state', 'published');
    $entity->save();
    $this->assertEquals(2, $entity->getRevisionId(), 'The new pending revision id should be 2');

    // Test latest revision is not the published one.
    $this->assertEquals('Published title', $storage->load($entity_id)->label());

    $this->container->get('cron')->run();

    // Test latest revision is now the published one.
    $this->assertEquals('Draft title', $storage->load($entity_id)->label());

    // Assert only one revision is created during the operation.
    $this->assertEquals(3, $storage->load($entity_id)->getRevisionId(), 'After publishing, the revision id should be 3');
  }

}
