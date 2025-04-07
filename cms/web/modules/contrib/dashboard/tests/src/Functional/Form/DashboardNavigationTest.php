<?php

namespace Drupal\Tests\dashboard\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\DashboardInterface;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\user\UserInterface;

/**
 * Test for dashboard navigation.
 *
 * @group dashboard
 */
class DashboardNavigationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard', 'toolbar'];

  /**
   * A Dashboard to check access to.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected DashboardInterface $dashboard;

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
  protected string $adminRole;

  /**
   * A role id with permissions to administer dashboards.
   *
   * @var string
   */
  protected string $toolbarRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing Dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $this->dashboard->save();

    $this->adminRole = $this->drupalCreateRole([
      'view existing_dashboard dashboard',
    ]);

    $this->toolbarRole = $this->drupalCreateRole([
      'view the administration theme',
      'access toolbar',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->adminRole);
    $this->adminUser->addRole($this->toolbarRole);
    $this->adminUser->save();
  }

  /**
   * Tests the existence of the dashboard navigation item.
   */
  public function testDashboardToolbarItem() {
    $this->drupalLogin($this->adminUser);

    // Assert that the dashboard navigation item is present in the HTML.
    $this->assertSession()->elementExists('css', '#toolbar-administration #toolbar-link-dashboard');

    $this->adminUser->removeRole($this->adminRole);
    $this->adminUser->save();

    $this->drupalGet('<front>');
    // Assert that the dashboard navigation item is not present in the HTML.
    $this->assertSession()->elementNotExists('css', '#toolbar-administration #toolbar-link-dashboard');
  }

}
