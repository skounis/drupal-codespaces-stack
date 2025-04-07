<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\dashboard\DashboardPermissions;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Tests for dashboard dynamic permissions.
 *
 * @group dashboard
 */
class DashboardPermissionsTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'dashboard',
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
  public function testPermissions() {
    $id = $this->randomMachineName();
    $label = $this->randomMachineName();
    Dashboard::create([
      'id' => $id,
      'label' => $label,
      'status' => TRUE,
      'weight' => 0,
    ])->save();

    $expected_permissions = [
      "view $id dashboard" => [
        'title' => "Access to <em class=\"placeholder\">$label</em> dashboard",
        'dependencies' => [
          'config' => [
            "dashboard.dashboard.$id",
          ],
        ],
      ],
    ];
    $permissions = (new DashboardPermissions())->permissions();
    $this->assertSame(array_keys($expected_permissions), array_keys($permissions));
    foreach ($expected_permissions as $key => $expected_permission) {
      $this->assertSame((string) $expected_permission['title'], (string) $permissions[$key]['title']);
      $this->assertSame($expected_permission['dependencies'], $permissions[$key]['dependencies']);
    }
  }

}
