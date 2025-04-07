<?php

namespace Drupal\Tests\dashboard\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\user\Entity\Role;

/**
 * Test for dashboard permissions form.
 *
 * @group dashboard
 */
class DashboardPermissionsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'block_test',
    'dashboard',
    'dashboard_test',
    'user',
  ];

  /**
   * A user with permission to administer dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected $role;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      'view test dashboard',
      'administer dashboard',
      'configure any layout',
      'administer permissions',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
  }

  /**
   * Tests dashboard forms local tasks.
   */
  public function testDashboardPermissionsForm() {
    $dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing',
      'weight' => 0,
    ]);
    $dashboard->save();

    $role = $this->drupalCreateRole([]);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/permissions');

    $this->assertSession()->checkboxNotChecked($role . '[view existing_dashboard dashboard]');
    $this->submitForm([$role . '[view existing_dashboard dashboard]' => TRUE], 'Save');

    $savedRole = Role::load($role);
    $this->assertSame(['view existing_dashboard dashboard'], $savedRole->getPermissions());
  }

  /**
   * Tests dashboard forms local tasks.
   */
  public function testDashboardFormLocalTasksWithPermissions() {
    $dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing',
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->drupalLogin($this->adminUser);

    // Check local actions in Edit form.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Preview');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[4]/a', 'Manage permissions');

    // Check local actions in Edit layout form.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/layout');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a[contains(@class, is-active)]', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Preview');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[4]/a', 'Manage permissions');

    // Check local actions in Preview tab.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/preview');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a[contains(@class, is-active)]', 'Preview');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[4]/a', 'Manage permissions');

    // Check local actions in Manage permissions tab.
    $this->drupalGet('admin/structure/dashboard/existing_dashboard/permissions');

    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a', 'Edit');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Edit layout');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Preview');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[4]/a[contains(@class, is-active)]', 'Manage permissions');

  }

}
