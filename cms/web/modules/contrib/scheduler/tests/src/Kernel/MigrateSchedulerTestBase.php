<?php

namespace Drupal\Tests\scheduler\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Base class for testing the migration of Drupal 7 configuration and data.
 */
abstract class MigrateSchedulerTestBase extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'scheduler',
    'text',
    'views',
  ];

}
