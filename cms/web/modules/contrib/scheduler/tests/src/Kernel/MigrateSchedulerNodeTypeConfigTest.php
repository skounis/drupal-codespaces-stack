<?php

namespace Drupal\Tests\scheduler\Kernel;

use Drupal\node\Entity\NodeType;

/**
 * Tests the migration of Drupal 7 Scheduler node type settings.
 *
 * @group scheduler_kernel
 */
class MigrateSchedulerNodeTypeConfigTest extends MigrateSchedulerTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('scheduler'),
      'tests',
      'fixtures',
      'node_type_config.php',
    ]));
    $this->installConfig(['scheduler']);
  }

  /**
   * Tests the migration of Scheduler settings per node type.
   */
  public function testNodeTypeSettingsMigration() {
    $this->migrateContentTypes();
    $article_config = NodeType::load('article');
    $this->assertEquals([
      'expand_fieldset' => 'when_required',
      'publish_enable' => TRUE,
      'publish_past_date' => 'error',
      'publish_required' => TRUE,
      'publish_revision' => TRUE,
      'publish_touch' => FALSE,
      'unpublish_enable' => TRUE,
      'unpublish_required' => TRUE,
      'unpublish_revision' => TRUE,
      'fields_display_mode' => 'vertical_tab',
    ], $article_config->get('third_party_settings')['scheduler']);

    $page_config = NodeType::load('page');
    $this->assertEquals([
      'expand_fieldset' => 'always',
      'publish_enable' => TRUE,
      'publish_past_date' => 'publish',
      'publish_required' => FALSE,
      'publish_revision' => FALSE,
      'publish_touch' => TRUE,
      'unpublish_enable' => FALSE,
      'unpublish_required' => FALSE,
      'unpublish_revision' => FALSE,
      'fields_display_mode' => 'fieldset',
    ], $page_config->get('third_party_settings')['scheduler']);
  }

}
