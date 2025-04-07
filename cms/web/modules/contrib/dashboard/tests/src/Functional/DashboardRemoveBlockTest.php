<?php

namespace Drupal\Tests\dashboard\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Remove block functionality.
 *
 * @group dashboard
 */
class DashboardRemoveBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dashboard_test',
    'dashboard',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
      'administer dashboard',
      'configure any layout',
      'view test dashboard',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->role);
    $this->adminUser->save();

    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'primary_local_tasks']);
  }

  /**
   * Tests the remove block logic.
   */
  public function testDashboardRemoveBlock() {
    // Login with adequate permissions.
    $this->drupalLogin($this->adminUser);

    // Check that test dashboard exists.
    $this->drupalGet('/admin/dashboard');
    $this->assertSession()->elementExists('css', '.dashboard--test');

    // Validate that 2 blocks are visible and no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextNotContains("You have unsaved changes.");
    $this->assertSession()->pageTextContains("Dashboard Text 1");
    $this->assertSession()->pageTextContains("Dashboard Text 2");

    // Remove one of the blocks.
    $this->drupalGet('/layout_builder/remove/block/dashboard/test/0/second/e4654a62-9bfb-40ac-923b-e49f9faf2314');
    $this->submitForm([], 'Remove');

    // Validate that one block is removed and there are unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextContains("You have unsaved changes.");
    $this->assertSession()->pageTextContains("Dashboard Text 1");
    $this->assertSession()->pageTextNotContains('Dashboard Text 2');

    // Save changes.
    $this->submitForm([], 'Save dashboard layout');

    // Validate that one block is removed and there are no unsaved changes.
    $this->drupalGet('/admin/structure/dashboard/test/layout');
    $this->assertSession()->pageTextNotContains("You have unsaved changes.");
    $this->assertSession()->pageTextContains("Dashboard Text 1");
    $this->assertSession()->pageTextNotContains('Dashboard Text 2');
  }

}
