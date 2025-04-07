<?php

namespace Drupal\Tests\focal_point\Kernel;

use Drupal\file\Entity\File;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests Focal Point configuration.
 *
 * @group focal_point
 */
class FocalPointMigrationTest extends MigrateDrupal7TestBase {

  /**
   * The crop storage.
   *
   * @var \Drupal\crop\CropStorageInterface
   */
  protected $cropStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_ui',
    'comment',
    'image',
    'node',
    'taxonomy',
    'text',
    'crop',
    'focal_point',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('crop');
    $this->installSchema('file', ['file_usage']);
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->cropStorage = $entity_type_manager->getStorage('crop');
    $this->installConfig(['comment', 'node', 'crop', 'focal_point']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('focal_point'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Tests focal_point data and widget settings migration.
   */
  public function testFocalPointMigration(): void {
    $this->startCollectingMessages();
    $this->executeMigrations([
      'd7_node_type',
      'd7_file',
      'd7_comment_type',
      'd7_taxonomy_vocabulary',
      'd7_field',
      'd7_field_instance',
      'd7_field_instance_widget_settings',
      'focal_point_crop_type',
      'focal_point_settings',
      'focal_point_crop',
    ]);
    $config_after = $this->config('core.entity_form_display.node.article.default')->getRawData();
    $type_after = $config_after['content']['field_image']['type'];
    $this->assertEquals('image_focal_point', $type_after);
    $file_3 = File::load(3);
    $image_uri = $file_3->getFileUri();
    $image_crop = $this->cropStorage->getCrop($image_uri, 'focal_point');
    $this->assertEquals(
      ['x' => 645, 'y' => 115],
      $image_crop->position()
    );
    $file_17 = File::load(17);
    $media_image_uri = $file_17->getFileUri();
    $media_image_crop = $this->cropStorage->getCrop($media_image_uri, 'focal_point');
    $this->assertEquals(
      ['x' => 614, 'y' => 100],
      $media_image_crop->position()
    );
  }

  /**
   * To provide source base path for test images.
   */
  protected function prepareMigration(MigrationInterface $migration) {
    if ($migration->getSourcePlugin()->getPluginId() === 'd7_file') {
      $source_plugin_conf = $migration->getSourceConfiguration();
      $source_plugin_conf['constants']['source_base_path'] = implode(
        DIRECTORY_SEPARATOR,
        [
          \Drupal::service('extension.list.module')->getPath('focal_point'),
          'tests',
          'fixtures',
          'files',
        ]
      );
      $migration->set('source', $source_plugin_conf);
    }
  }

}
