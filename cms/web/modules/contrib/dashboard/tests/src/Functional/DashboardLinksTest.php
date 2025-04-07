<?php

namespace Drupal\Tests\Dashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\user\Entity\Role;

/**
 * Tests the dashboard links and local actions.
 *
 * @group dashboard
 */
class DashboardLinksTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard'];

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
  protected $adminRole;

  /**
   * A user with permission to access individual dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $dashboardUser;

  /**
   * A role id with permissions to access individual dashboards.
   *
   * @var string
   */
  protected $dashboardRole;

  /**
   * Dashboard entity for testing purposes.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboardFoo;

  /**
   * Dashboard entity for testing purposes.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboardBar;

  /**
   * Dashboard entity for testing purposes.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboardBaz;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dashboardFoo = Dashboard::create([
      'id' => 'foo',
      'label' => 'Foo',
      'status' => TRUE,
      'weight' => 1,
    ]);
    $this->dashboardFoo->save();
    $this->dashboardBar = Dashboard::create([
      'id' => 'bar',
      'label' => 'Bar',
      'status' => TRUE,
      'weight' => 2,
    ]);
    $this->dashboardBar->save();
    $this->dashboardBaz = Dashboard::create([
      'id' => 'baz',
      'label' => 'Baz',
      'status' => TRUE,
      'weight' => 3,
    ]);
    $this->dashboardBaz->save();

    $this->adminRole = $this->drupalCreateRole([
      'view the administration theme',
      'administer dashboard',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->adminRole);
    $this->adminUser->save();

    $this->dashboardRole = $this->drupalCreateRole([
      'view the administration theme',
      'view foo dashboard',
      'view bar dashboard',
      'view baz dashboard',
    ]);

    $this->dashboardUser = $this->drupalCreateUser();
    $this->dashboardUser->addRole($this->dashboardRole);
    $this->dashboardUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
  }

  /**
   * Tests dashboard local tasks behavior for user with admin permission.
   */
  public function testDashboardLocalTasksAdminUser() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests dashboard local tasks behavior for user with admin permission.
   */
  public function testDashboardLocalTasksDashboardUser() {
    $this->drupalLogin($this->dashboardUser);

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Foo');
    $this->assertSession()->elementExists('css', '.dashboard--foo');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Bar');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Baz');

    $this->dashboardFoo->setWeight(100)->save();

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Bar');
    $this->assertSession()->elementExists('css', '.dashboard--bar');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Baz');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a', 'Foo');

    $this->dashboardBar->disable()->save();

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Baz');
    $this->assertSession()->elementExists('css', '.dashboard--baz');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Foo');
    $this->assertSession()->elementNotExists('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[3]/a');

    $this->dashboardBaz->disable()->save();

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementExists('css', '.dashboard--foo');
    $this->assertSession()->elementNotExists('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a');

    $this->dashboardBar->enable()->save();
    $this->dashboardBaz->enable()->save();
    Role::load($this->dashboardRole)
      ->revokePermission('view baz dashboard')
      ->save();

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[1]/a[contains(@class, is-active)]', 'Bar');
    $this->assertSession()->elementExists('css', '.dashboard--bar');
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="block-primary-local-tasks"]/ul/li[2]/a', 'Foo');
  }

}
