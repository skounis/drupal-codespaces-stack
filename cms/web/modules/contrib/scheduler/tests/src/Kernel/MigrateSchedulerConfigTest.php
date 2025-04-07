<?php

namespace Drupal\Tests\scheduler\Kernel;

/**
 * Tests the migration of Drupal 7 scheduler configuration.
 *
 * @group scheduler_kernel
 */
class MigrateSchedulerConfigTest extends MigrateSchedulerTestBase {

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
      'scheduler_config.php',
    ]));
    $this->installConfig(['scheduler']);
  }

  /**
   * Tests the migration of Scheduler global settings.
   */
  public function testGlobalSettingsMigration() {
    $config_before = $this->config('scheduler.settings');
    $this->assertFalse($config_before->get('allow_date_only'));
    $this->assertSame('00:00:00', $config_before->get('default_time'));
    $this->assertFalse($config_before->get('hide_seconds'));

    // See /migrations/d7_scheduler_settings.yml.
    $this->executeMigration('d7_scheduler_settings');

    $config_after = $this->config('scheduler.settings');
    $this->assertTrue($config_after->get('allow_date_only'));
    $this->assertSame('00:00:38', $config_after->get('default_time'));
    $this->assertTrue($config_after->get('hide_seconds'));

  }

}
