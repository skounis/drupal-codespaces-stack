<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\dashboard\Entity\Dashboard;

/**
 * Tests for dashboard access control handler.
 *
 * @group dashboard
 */
class DashboardAccessControlHandlerTest extends KernelTestBase {

  use UserCreationTrait {
    createRole as drupalCreateRole;
    createUser as drupalCreateUser;
  }

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'dashboard',
    'system',
    'user',
  ];

  /**
   * Dashboard entity for testing purposes.
   *
   * @var \Drupal\dashboard\DashboardInterface
   */
  protected $dashboard;

  /**
   * An anonymous user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $anonymousUser;

  /**
   * A user with permission to view the specific dashboard.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $dashboardUser;

  /**
   * A user with permission to administer dashboards.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The content_moderation_state access control handler.
   *
   * @var \Drupal\dashboard\DashboardAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('action');
    $this->installEntitySchema('dashboard');
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('dashboard');

    $this->dashboard = Dashboard::create([
      'id' => 'dashboard',
      'status' => TRUE,
      'weight' => 0,
    ]);
    $this->dashboard->save();

    $dashboard_role = $this->drupalCreateRole([
      'view dashboard dashboard',
    ]);
    $admin_role = $this->drupalCreateRole([
      'administer dashboard',
    ]);
    $this->anonymousUser = new AnonymousUserSession();
    $this->dashboardUser = $this->drupalCreateUser();
    $this->dashboardUser->addRole($dashboard_role);
    $this->adminUser = $this->drupalCreateUser();
    $this->adminUser->addRole($admin_role);
  }

  /**
   * Tests access to view published dashboard.
   */
  public function testViewPublishedAccess() {
    $this->assertFalse($this->dashboard->access('view', $this->anonymousUser));
    $this->assertTrue($this->dashboard->access('view', $this->dashboardUser));
    $this->assertFalse($this->dashboard->access('view', $this->adminUser));
  }

  /**
   * Tests access to view unpublished dashboard.
   */
  public function testViewUnpublishedAccess() {
    $this->dashboard->setStatus(FALSE)->save();

    $this->assertFalse($this->dashboard->access('view', $this->anonymousUser));
    $this->assertFalse($this->dashboard->access('view', $this->dashboardUser));
    $this->assertFalse($this->dashboard->access('view', $this->adminUser));
  }

  /**
   * Tests access to preview published dashboard.
   */
  public function testPreviewPublishedAccess() {
    $this->assertFalse($this->dashboard->access('preview', $this->anonymousUser));
    $this->assertTrue($this->dashboard->access('preview', $this->dashboardUser));
    $this->assertTrue($this->dashboard->access('preview', $this->adminUser));
  }

  /**
   * Tests access to preview unpublished dashboard.
   */
  public function testPreviewUnpublishedAccess() {
    $this->dashboard->setStatus(FALSE)->save();

    $this->assertFalse($this->dashboard->access('preview', $this->anonymousUser));
    $this->assertTrue($this->dashboard->access('preview', $this->dashboardUser));
    $this->assertTrue($this->dashboard->access('preview', $this->adminUser));
  }

}
