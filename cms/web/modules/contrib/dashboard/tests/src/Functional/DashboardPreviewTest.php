<?php

namespace Drupal\Tests\Dashboard\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Test for dashboard layout builder preview looking like the dashboard.
 *
 * @group dashboard
 */
class DashboardPreviewTest extends BrowserTestBase {

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
  public function testPreview() {
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
    $this->assertSession()->elementExists('css', '#layout-builder');
    $layout_builder_element = $this->getSession()->getPage()->findById('layout-builder');
    $wrapper = $layout_builder_element->getParent();
    $classes = $wrapper->getAttribute('class');
    $this->assertSame($classes, 'dashboard dashboard--existing-dashboard');
  }

}
