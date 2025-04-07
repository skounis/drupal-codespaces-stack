<?php

namespace Drupal\Tests\editoria11y\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests with node access enabled.
 *
 * @noinspection PhpUndefinedMethodInspection
 *
 * @group content_moderation
 */
class SchemaTest extends KernelTestBase {

  /**
   * Editoria11y schema test.
   *
   * {@inheritdoc}
   */
  protected static $modules = [
    'editoria11y',
  ];

  /**
   * Set up module.
   *
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('editoria11y', ['editoria11y_dismissals']);
  }

  /**
   * A simple test to confirm the schema has been successfully installed.
   */
  public function testConfirmSchema() {
    try {
      $database = \Drupal::database();
      $query = $database->select('editoria11y_dismissals', 'efd')
        ->fields("efd", ["uid"]);
      $query->execute();
      $this->assertTrue(TRUE);
    }
    catch (\Exception $e) {
      $this->fail("Exception thrown testing for schema presence: {$e->getMessage()}");
    }
  }

}
