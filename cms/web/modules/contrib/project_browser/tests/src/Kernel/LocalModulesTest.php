<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser\Plugin\ProjectBrowserSource\LocalModules;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;

/**
 * Tests the LocalModules source plugin.
 *
 * @covers \Drupal\project_browser\Plugin\ProjectBrowserSource\LocalModules
 * @group project_browser
 */
final class LocalModulesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser'];

  /**
   * Tests that the plugin sets the machine_name query filter if needed.
   */
  public function testDecoratorFiltersByMachineName(): void {
    $expectation = function (array $query): bool {
      return $query['machine_name'] === 'module_one,module_two,module_three';
    };
    $mock_decorated_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $mock_decorated_source->expects($this->atLeastOnce())
      ->method('getProjects')
      ->with($this->callback($expectation))
      ->willReturn(new ProjectsResultsPage(0, [], 'Decorated', 'decorated'));

    $plugin = new LocalModules(
      $mock_decorated_source,
      [
        'package_names' => [
          'drupal/module_one',
          'drupal/module_two',
          'drupal/module_three',
        ],
      ],
      'local_modules',
      ['label' => 'Decorator'],
    );
    $result = $plugin->getProjects();
    $this->assertSame('local_modules', $result->pluginId);
    $this->assertSame('Decorator', $result->pluginLabel);
  }

  /**
   * Tests that the plugin skips extra filtering if no modules are installed.
   */
  public function testNoFilteringIfNoModulesAreInstalled(): void {
    $expectation = function (array $query): bool {
      return !array_key_exists('machine_name', $query);
    };
    $mock_decorated_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $mock_decorated_source->expects($this->atLeastOnce())
      ->method('getProjects')
      ->with($this->callback($expectation))
      ->willReturn(new ProjectsResultsPage(0, [], 'Decorated', 'decorated'));

    $plugin = new LocalModules(
      $mock_decorated_source,
      [
        // Simulate a situation where no packages are installed.
        'package_names' => [],
      ],
      'local_modules',
      ['label' => 'Decorator'],
    );
    $result = $plugin->getProjects();
    $this->assertSame('local_modules', $result->pluginId);
    $this->assertSame('Decorator', $result->pluginLabel);
  }

  /**
   * Tests that the decorator properly populates non-volatile project storage.
   */
  public function testProjectStoreIsPopulated(): void {
    $this->container->get(ModuleInstallerInterface::class)->install([
      'project_browser_test',
    ]);
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['local_modules', 'project_browser_test_mock'])
      ->save();
    /** @var \Drupal\project_browser\EnabledSourceHandler $source_handler */
    $source_handler = $this->container->get(EnabledSourceHandler::class);
    $source_handler->getProjects('local_modules');
    // Ensure that the decorator "took ownership" of the projects returned by
    // the decorated plugin.
    $source_handler->getStoredProject('local_modules/cream_cheese');
    // Even though the mock plugin (which is being decorated by local_modules)
    // did the actual work, the non-volatile storage shouldn't be aware of that.
    $this->expectExceptionMessage("Project 'project_browser_test_mock/cream_cheese' was not found in non-volatile storage.");
    $source_handler->getStoredProject('project_browser_test_mock/cream_cheese');
  }

}
