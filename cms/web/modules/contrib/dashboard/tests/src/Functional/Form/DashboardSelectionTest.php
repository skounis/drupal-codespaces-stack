<?php

namespace Drupal\Tests\dashboard\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests for dashboard selection logic.
 *
 * @group dashboard
 */
class DashboardSelectionTest extends BrowserTestBase {

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
  protected UserInterface $adminUser;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected string $role;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->role = $this->drupalCreateRole([
      'view the administration theme',
      'administer dashboard',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Tests the default dashboard selection logic.
   */
  public function testDashboardSelect() {
    $dashboard1 = Dashboard::create([
      'id' => 'dashboard1',
      'label' => 'Dashboard 1',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $dashboard1->save();

    $dashboard2 = Dashboard::create([
      'id' => 'dashboard2',
      'label' => 'Dashboard 2',
      'weight' => -10,
      'status' => TRUE,
    ]);
    $dashboard2->save();

    $role = Role::load($this->role);

    // Anonymous user cannot access to dashboard page.
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);

    // User has no specific permissions to access any dashboard.
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(403);

    // User can access only to dashboard1.
    $role->grantPermission('view dashboard1 dashboard')->save();
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--dashboard1');

    // User can access to both dashboards.
    $role->grantPermission('view dashboard2 dashboard')->save();
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--dashboard2');

    // Dashboard1 is disabled and inaccessible.
    $dashboard1->disable();
    $dashboard1->save();
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--dashboard2');

    // Dashboard2 is disabled and inaccessible.
    $dashboard1->enable();
    $dashboard1->save();
    $dashboard2->disable();
    $dashboard2->save();
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.dashboard--dashboard1');

    // Both dashboards are disabled and inaccessible.
    $dashboard1->disable();
    $dashboard1->save();
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->statusCodeEquals(403);
  }

}
