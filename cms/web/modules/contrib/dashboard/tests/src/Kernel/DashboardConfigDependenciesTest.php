<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Tests for dashboard config dependencies.
 *
 * @group dashboard
 */
class DashboardConfigDependenciesTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'dashboard',
    'layout_discovery',
    'announcements_feed',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('dashboard');
  }

  /**
   * Tests dynamic permissions.
   */
  public function testCalculateDependencies() {
    $id = $this->randomMachineName();
    $label = $this->randomMachineName();
    /** @var \Drupal\dashboard\Entity\Dashboard $dashboard */
    $dashboard = Dashboard::create([
      'id' => $id,
      'label' => $label,
      'status' => TRUE,
      'weight' => 0,
    ]);
    $dashboard->save();

    // When layout builder isn't used, we don't depend on anything.
    $dependencies = $dashboard->calculateDependencies()->getDependencies();
    $this->assertEmpty($dependencies);

    // If we add a layout section, we depend on layout_builder module.
    $section = new Section('layout_onecol');
    $dashboard->appendSection($section);
    $dependencies = $dashboard->calculateDependencies()->getDependencies();

    $expected_dependencies = [
      'module' => [
        'layout_builder',
        'layout_discovery',
      ],
    ];
    $this->assertSame(array_keys($expected_dependencies), array_keys($dependencies));
    foreach ($expected_dependencies as $key => $expected_dependency) {
      $this->assertSame($expected_dependency, $dependencies[$key]);
    }

    // Add announcement_block block and confirm that dependencies are updated.
    $component = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'region' => 'content',
      'weight' => 0,
      'configuration' => [
        'id' => 'announce_block',
        'label' => 'Announcements',
        'label_display' => 1,
        'provider' => 'announcements_feed',
      ],
      'additional' => [],
    ];

    $section_component = SectionComponent::fromArray($component);
    $section->appendComponent($section_component);

    $dependencies = $dashboard->calculateDependencies()->getDependencies();

    $expected_dependencies = [
      'module' => [
        'announcements_feed',
        'layout_builder',
        'layout_discovery',
      ],
    ];
    $this->assertSame(array_keys($expected_dependencies), array_keys($dependencies));
    foreach ($expected_dependencies as $key => $expected_dependency) {
      $this->assertSame($expected_dependency, $dependencies[$key]);
    }
  }

}
