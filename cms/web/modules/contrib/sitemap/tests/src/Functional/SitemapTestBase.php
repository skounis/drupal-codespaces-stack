<?php

namespace Drupal\Tests\sitemap\Functional;

/**
 * Tests the landing and admin pages of the sitemap.
 *
 * @group sitemap
 */
abstract class SitemapTestBase extends SitemapBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['sitemap'];

  /**
   * User accounts.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $userAdmin;

  /**
   * {@inheritDoc}
   */
  public $userView;

  /**
   * {@inheritDoc}
   */
  public $userNoAccess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create user with admin permissions.
    $this->userAdmin = $this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
    ]);

    // Create user with view permissions.
    $this->userView = $this->drupalCreateUser([
      'access sitemap',
    ]);

    // Create user without any sitemap permissions.
    $this->userNoAccess = $this->drupalCreateUser();
  }

}
