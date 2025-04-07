<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test the display of menus based on sitemap settings.
 */
abstract class SitemapMenuTestBase extends SitemapBrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sitemap', 'node', 'menu_ui'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Anonymous user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anonUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an Article node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Create user then login.
    $this->adminUser = $this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      'administer menu',
      'administer nodes',
      'create article content',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create anonymous user for use too.
    $this->anonUser = $this->drupalCreateUser([
      'access sitemap',
    ]);
  }

  /**
   * Creates a node and adds it to the menu.
   *
   * @param string $menu_id
   *   The menu id.
   */
  protected function createNodeInMenu($menu_id) {
    // Create test node with enabled menu item.
    $edit = [
      'title[0][value]' => $this->randomString(),
      'menu[enabled]' => TRUE,
      'menu[title]' => $this->randomString(),
      'menu[menu_parent]' => $menu_id . ':',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
  }

}
