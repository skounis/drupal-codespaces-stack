<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests project_browser module installation.
 *
 * @group project_browser
 */
final class InstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Module handler to ensure installed modules.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
    $this->moduleHandler = $this->container->get('module_handler');
  }

  /**
   * Reloads services used by this test.
   */
  protected function reloadServices(): void {
    $this->rebuildContainer();
    $this->moduleHandler = $this->container->get('module_handler');
  }

  /**
   * Tests that the module is installable.
   */
  public function testInstallation(): void {
    $edit = [];
    $edit['modules[project_browser][enable]'] = 'project_browser';
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');
    // @todo Convert this to pageTextContains(), and only look for `installed`,
    //   once Drupal 10 support is dropped.
    $this->assertSession()->pageTextMatches('/Module Project Browser has been (installed|enabled)\./');
    $this->assertSession()->statusCodeEquals(200);
    $this->reloadServices();
    $this->assertTrue($this->moduleHandler->moduleExists('project_browser'));
    $this->assertFalse($this->moduleHandler->moduleExists('package_manager'));
  }

}
