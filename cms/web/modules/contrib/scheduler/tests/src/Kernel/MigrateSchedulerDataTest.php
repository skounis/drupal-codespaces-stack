<?php

namespace Drupal\Tests\scheduler\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests the migration of Drupal 7 scheduler data.
 *
 * @group scheduler_kernel
 */
class MigrateSchedulerDataTest extends MigrateSchedulerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('scheduler'),
      'tests',
      'fixtures',
      'scheduler_data.php',
    ]);
  }

  /**
   * Tests the migration of Scheduler data into node.
   */
  public function testSchedulerDataMigration() {
    $this->installConfig(['node']);
    $this->installEntitySchema('node');
    $this->executeMigrations([
      'd7_node_type',
      'd7_node_complete',
    ]);
    $nodes = Node::loadMultiple([1, 2, 3]);
    $this->assertSame(['1647751855', '1647838255'], [
      $nodes[1]->publish_on->value,
      $nodes[1]->unpublish_on->value,
    ]);
    $this->assertSame(['1647579055', NULL], [
      $nodes[2]->publish_on->value,
      $nodes[2]->unpublish_on->value,
    ]);
    $this->assertSame([NULL, NULL], [
      $nodes[3]->publish_on->value,
      $nodes[3]->unpublish_on->value,
    ]);
  }

}
