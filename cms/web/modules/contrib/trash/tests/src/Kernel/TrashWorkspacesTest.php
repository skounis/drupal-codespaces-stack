<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests Trash integration with Workspaces.
 *
 * @group trash
 */
class TrashWorkspacesTest extends TrashKernelTestBase {

  use WorkspaceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workspaces',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->workspaceManager = \Drupal::service('workspaces.manager');

    $this->installSchema('workspaces', ['workspace_association']);
    $this->installEntitySchema('workspace');

    $this->workspaces['stage'] = Workspace::create(['id' => 'stage', 'label' => 'Stage']);
    $this->workspaces['stage']->save();

    $this->setCurrentUser($this->createUser([
      'view any workspace',
    ]));
  }

  /**
   * Test trashing entities in a workspace.
   */
  public function testDeletion(): void {
    $live_node = $this->createNode(['type' => 'article']);
    $live_node->save();

    // Activate a workspace and delete the node.
    $this->switchToWorkspace('stage');

    $ws_node = $this->createNode(['type' => 'article']);
    $ws_node->save();

    $live_node->delete();
    $ws_node->delete();

    $this->assertTrue(trash_entity_is_deleted($live_node));
    $this->assertTrue(trash_entity_is_deleted($ws_node));

    // Check loading the deleted nodes in a workspace.
    $storage = $this->entityTypeManager->getStorage('node');

    $this->assertNull($storage->load($live_node->id()));
    $this->assertNull($storage->loadRevision($live_node->getRevisionId()));

    $this->assertNull($storage->load($ws_node->id()));
    $this->assertNull($storage->loadRevision($ws_node->getRevisionId()));

    // Switch back to Live and check that the nodes are not marked as deleted.
    $this->switchToLive();

    $live_node = $storage->load($live_node->id());
    $this->assertNotNull($live_node);
    $this->assertTrue($live_node->isPublished());
    $this->assertNotNull($storage->loadRevision($live_node->getRevisionId()));
    $this->assertFalse(trash_entity_is_deleted($live_node));

    $ws_node = $storage->load($ws_node->id());
    $this->assertNotNull($ws_node);
    $this->assertFalse($ws_node->isPublished());
    $this->assertNotNull($storage->loadRevision($ws_node->getRevisionId()));
    $this->assertFalse(trash_entity_is_deleted($ws_node));

    // Publish the workspace and check that both nodes are now deleted in Live.
    $this->workspaces['stage']->publish();

    $this->assertNull($storage->load($live_node->id()));
    $this->assertNull($storage->load($ws_node->id()));
  }

  /**
   * Test 'purge' entity access in a workspace.
   */
  public function testPurgeAccess(): void {
    $this->setCurrentUser($this->createUser([
      'access content',
      'view any workspace',
      'purge node entities',
    ]));

    $live_node = $this->createNode(['type' => 'article']);
    $live_node->save();

    // Activate a workspace and delete the node.
    $this->switchToWorkspace('stage');

    $ws_node = $this->createNode(['type' => 'article']);
    $ws_node->save();

    $live_node->delete();
    $ws_node->delete();

    $this->assertFalse($live_node->access('purge'));
    $this->assertTrue($ws_node->access('purge'));
  }

}
