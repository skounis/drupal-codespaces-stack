<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\trash_test\Entity\TrashTestEntity;
use Drupal\user\Entity\User;

/**
 * Tests basic trash functionality.
 *
 * @group trash
 */
class TrashKernelTest extends TrashKernelTestBase {

  /**
   * Test trashing entities.
   */
  public function testDeletion(): void {
    $entity = TrashTestEntity::create();
    assert($entity instanceof ContentEntityInterface);
    $entity->save();
    $entity_id = $entity->id();

    $this->assertNotNull(TrashTestEntity::load($entity_id));
    $this->assertFalse(trash_entity_is_deleted($entity));
    $this->assertTrue($entity->get('deleted')->isEmpty());
    $this->assertNull($entity->get('deleted')->value);

    $entity->delete();

    // Test the default 'active' trash context.
    $entity = TrashTestEntity::load($entity_id);
    $this->assertNull($entity, 'Deleted entities can not be loaded in the default (active) trash context.');

    // Test the 'ignore' trash context.
    $entity = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_id) {
      return TrashTestEntity::load($entity_id);
    });
    assert($entity instanceof ContentEntityInterface);
    $this->assertNotNull($entity, 'Deleted entities can still be loaded in the "ignore" trash context.');
    $this->assertTrue(trash_entity_is_deleted($entity));
    $this->assertEquals(\Drupal::time()->getRequestTime(), $entity->get('deleted')->value);

    $second_entity = TrashTestEntity::create();
    $second_entity->save();
    $second_entity_id = $second_entity->id();

    // Test the default 'active' trash context for multiple load.
    $entities = TrashTestEntity::loadMultiple();
    $this->assertCount(1, $entities);
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());

    // Test the 'ignore' trash context  for multiple load.
    $entities = $this->getTrashManager()->executeInTrashContext('ignore', function () {
      return TrashTestEntity::loadMultiple();
    });
    $this->assertCount(2, $entities);
    $this->assertEquals($entity_id, $entities[$entity_id]->id());
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());
  }

  /**
   * @covers ::trash_entity_is_deleted
   */
  public function testDisabledEntityType(): void {
    // Check a node bundle that's not trash-enabled.
    $nonDeletableNode = $this->createNode(['type' => 'page']);
    $nonDeletableNode->save();
    $this->assertFalse(trash_entity_is_deleted($nonDeletableNode));

    $nonDeletableNode->delete();
    $nonDeletableNode = Node::load($nonDeletableNode->id());
    $this->assertNull($nonDeletableNode);

    // Check an entity type that's not trash-enabled.
    $nonDeletableEntity = $this->createUser();
    $nonDeletableEntity->save();
    $this->assertFalse(trash_entity_is_deleted($nonDeletableEntity));

    $nonDeletableEntity->delete();
    $nonDeletableEntity = User::load($nonDeletableEntity->id());
    $this->assertNull($nonDeletableEntity);
  }

  /**
   * @covers \Drupal\trash\TrashManager::isEntityTypeEnabled
   *
   * @dataProvider providerIsEntityTypeEnabled
   */
  public function testIsEntityTypeEnabled($entity_type_id, $bundle, $enabled): void {
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type_id, $bundle));
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type, $bundle));
  }

  /**
   * Data provider for testIsEntityTypeEnabled().
   */
  public static function providerIsEntityTypeEnabled(): array {
    return [
      // An entity type with a single bundle enabled.
      ['node', NULL, TRUE],
      ['node', 'article', TRUE],
      ['node', 'page', FALSE],
      // An entity type with all bundles enabled.
      ['trash_test_entity', NULL, TRUE],
      ['trash_test_entity', 'random_bundle_id', TRUE],
      // A disabled entity type.
      ['media', NULL, FALSE],
      ['media', 'random_bundle_id', FALSE],
    ];
  }

}
