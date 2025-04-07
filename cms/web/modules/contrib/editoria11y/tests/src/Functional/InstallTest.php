<?php

namespace Drupal\Tests\editoria11y\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic test to confirm the module installs OK.
 *
 * @noinspection PhpUndefinedMethodInspection
 *
 * @group editoria11y
 */
class InstallTest extends BrowserTestBase {
  /**
   * Select theme.
   *
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Install modules.
   *
   * {@inheritdoc}
   */
  protected static $modules = ['editoria11y', 'user', 'views'];

  /**
   * Basic test to make sure we can access the configuration page.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testConfigurationPage() {

    $user = $this->setUpAdmin();
    $route = Url::fromRoute("editoria11y.settings");

    $this->drupalLogin($user);
    $this->drupalGet($route);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("Editoria11y Settings");
    $this->drupalLogout();
  }

  /**
   * Define a new administrator user.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUpAdmin() : AccountInterface {
    return $this->createUser([
      'administer editoria11y checker',
      'view editoria11y checker',
    ]);
  }

}
