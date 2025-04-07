<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for access to dashboard controller.
 *
 * @group dashboard
 */
class DashboardAccessTest extends BrowserTestBase {

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
   * A user with permission to view dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A role id with permissions to view dashboards.
   *
   * @var string
   */
  protected $role;

  /**
   * A Dashboard to check access to.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboard;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing dashboard',
      'status' => TRUE,
      'weight' => '8',
    ]);
    $this->dashboard->save();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      'view existing_dashboard dashboard',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Checks that user can access if at least one dashboard is available.
   */
  public function testAccessAvailableDashboard() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/dashboard');

    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Checks that user cannot access if no dashboard is available.
   */
  public function testAccessUnavailableDashboard() {
    $this->dashboard->setStatus(FALSE)->save();
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/dashboard');

    $this->assertSession()->statusCodeEquals(403);

    $this->dashboard->delete();

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Checks that user one can access every dashboard available without any role.
   */
  public function testAccessAvailableUserOneDashboard() {
    $another_dashboard = Dashboard::create([
      'id' => 'another_dashboard',
      'label' => 'Another dashboard',
      'status' => TRUE,
      'weight' => '-10',
    ]);
    $another_dashboard->save();

    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--another-dashboard');

    $this->drupalGet('/admin/dashboard/existing_dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--existing-dashboard');

    $this->drupalGet('/admin/dashboard/another_dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--another-dashboard');
  }

}
