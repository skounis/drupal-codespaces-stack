<?php

namespace Drupal\Tests\klaro\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the access permissions for klaro.
 */
class PermissionsTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'klaro',
  ];
  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Check if the admin interface can and cannot be reached.
   *
   * @var void
   */
  public function test() {
    $assert_session = $this->assertSession();
    // Get interface without user/credentials.
    $this->drupalGet('admin/config/user-interface/klaro');
    $assert_session->statusCodeEquals(403);

    // Enter user necessary for access.
    $adminUser = $this->drupalCreateUser(['administer klaro']);
    $this->drupalLogin($adminUser);

    // Check if user interface can be reached.
    $this->drupalGet('admin/config/user-interface/klaro');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Klaro! settings');
  }

}
