<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash_test\Entity\TrashTestEntity;

/**
 * Tests entity query integration for Trash.
 *
 * @group trash
 */
class EntityQueryTest extends TrashKernelTestBase {

  /**
   * Tests that deleted entities are excluded from entity query results.
   */
  public function testQueryWithoutDeletedAccess(): void {
    $entities = [];
    $entity_storage = \Drupal::entityTypeManager()->getStorage('trash_test_entity');

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in an entity query.
    $this->assertCount(5, $entity_storage->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(5, $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute());

    // Delete the first three of them. They should no longer accessible via the
    // entity query.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }
    $this->assertCount(2, $entity_storage->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(2, $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute());

    // Check that deleted entities can still be retrieved by an entity query if
    // the trash context is disabled.
    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage->getQuery()->accessCheck(FALSE)->execute();
    });
    $this->assertCount(5, $result);

    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute();
    });
    $this->assertCount(5, $result);
  }

}
