<?php

namespace Drupal\Tests\Dashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for dashboard text block.
 *
 * @group dashboard
 */
class DashboardTextBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dashboard', 'layout_builder'];

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminRole = $this->drupalCreateRole([
      'administer dashboard',
      'configure any layout',
    ]);

    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($this->adminRole);
    $this->adminUser->save();

  }

  /**
   * Tests the block text addition.
   */
  public function testBlockText() {
    $dashboard = Dashboard::create([
      'id' => 'existing_dashboard',
      'label' => 'Existing Dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $dashboard->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/dashboard/existing_dashboard/layout');
    $this->assertSession()
      ->titleEquals('Edit layout for existing_dashboard | Drupal');
    $this->assertTrue(TRUE);
    $page = $this->getSession()->getPage();

    // Add text block and save.
    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->pressButton('Add section');
    $page->clickLink('Add block');
    $page->clickLink('Dashboard Text');

    $this->submitForm([
      'settings[label]' => 'Block title',
      'settings[text][value]' => 'Block text',
    ], 'Add block');
    $page->pressButton('Save dashboard layout');

    // Confirm that text block is added and config stored.
    $this->assertSession()->statusMessageContains(' Updated dashboard Existing Dashboard layout.', 'status');
    $this->drupalGet('/admin/structure/dashboard/existing_dashboard/layout');
    $this->assertSession()->pageTextContains('Block title');
    $this->assertSession()->pageTextContains('Block text');
  }

}
