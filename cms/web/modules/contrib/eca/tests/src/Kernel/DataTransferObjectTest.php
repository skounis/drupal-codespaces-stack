<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for data transfer objects.
 *
 * @group eca
 * @group eca_core
 */
class DataTransferObjectTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    // Create an Article content type.
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
  }

  /**
   * Tests collecting data from multiple sources and saving them at once.
   */
  public function testDtoSave(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
      'uid' => 0,
    ]);
    $dto = DataTransferObject::create();
    $dto->set('title', $node->get('title'));
    $user = User::load(1);
    $dto->set('username', $user->get('name'));
    $node_type = NodeType::load('article');
    $dto->set('node_type', $node_type);

    $new_title = $this->randomMachineName();
    $dto->get('title')->setValue($new_title);
    $this->assertEquals($new_title, $node->title->value);

    $new_username = $this->randomMachineName();
    $dto->get('username')->setValue($new_username);
    $this->assertEquals($new_username, $user->name->value);

    $dto->get('node_type')->getValue()->set('name', 'ECA Article');
    $this->assertEquals('ECA Article', $node_type->get('name'));

    $this->assertTrue($node->isNew());
    $dto->saveData();
    $this->assertFalse($node->isNew());
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals($new_title, $node->title->value);
    $user = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged(1);
    $this->assertEquals($new_username, $user->name->value);
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->loadUnchanged('article');
    $this->assertEquals('ECA Article', $node_type->get('name'));
  }

  /**
   * Tests removing values.
   */
  public function testRemove(): void {
    $user1 = User::create(['name' => 'user1']);
    $user1->save();
    $user2 = User::create(['name' => 'user2']);
    $user2->save();
    $user3 = User::create(['name' => 'user3']);
    $user3->save();
    $user4 = User::create(['name' => 'user4']);
    $user4->save();
    $dto = DataTransferObject::create([$user1, $user2, $user3]);
    $this->assertSame(3, $dto->count());
    $item = $dto->remove($user4);
    $this->assertNull($item);
    $this->assertSame(3, $dto->count());
    $item = $dto->remove($user2);
    $this->assertNotNull($item);
    $this->assertSame(2, $dto->count());
    $cloned_user1 = clone $user1;
    $item = $dto->remove($cloned_user1);
    $this->assertNotNull($item);
    $this->assertSame(1, $dto->count());
  }

}
