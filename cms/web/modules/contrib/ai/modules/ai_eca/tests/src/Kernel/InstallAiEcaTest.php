<?php

namespace Drupal\Tests\ai_eca\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_eca and its dependencies.
 *
 * @group ai_eca
 */
class InstallAiEcaTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'ai', 'eca'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_eca']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_eca'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
