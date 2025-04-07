<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\sitemap\Traits\SitemapTestTrait;

/**
 * Test the display of menus based on sitemap settings.
 *
 * @group sitemap
 */
class SitemapMenuTest extends SitemapMenuTestBase {

  use SitemapTestTrait;
  use StringTranslationTrait;

  /**
   * Tests the menu title.
   */
  public function testMenuTitle() {
    // Configure module to show main menu.
    $this->saveSitemapForm(['plugins[menu:main][enabled]' => TRUE]);

    // Add a node to the menu.
    $this->createNodeInMenu('main');

    $this->titleTest('Main navigation', 'menu', 'main', TRUE);
  }

  /**
   * Tests menus.
   */
  public function testMenus() {
    // Assert that main menu is not included in the sitemap by default.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--menu");
    $this->assertEquals(\count($elements), 0, 'Main menu is not included.');

    // Make sure "Show disabled menu items" control is hidden for users without
    // permission to see it.
    $this->drupalGet('admin/config/search/sitemap');
    $this->assertSession()->fieldNotExists('plugins[menu:main][settings][show_disabled]');

    $this->drupalLogin($this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      'administer menu',
      'administer nodes',
      'create article content',
      'show disabled menu items on sitemap',
    ]));

    // Configure module to show main menu, with enabled menu items only.
    $edit = [
      'plugins[menu:main][enabled]' => TRUE,
      'plugins[menu:main][settings][show_disabled]' => FALSE,
      'plugins[menu:main][settings][title]' => 'Main navigation',
    ];
    $this->saveSitemapForm($edit);

    // Create a node with an enabled menu item.
    $node_1_title = $this->randomString();
    $edit = [
      'title[0][value]' => $node_1_title,
      'menu[enabled]' => TRUE,
      'menu[title]' => $node_1_title,
      // In order to make main navigation menu displayed, there must be at least
      // one child menu item of that menu.
      'menu[menu_parent]' => 'main:',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    // Create a node for a disabled menu item.
    $node_2_title = $this->randomString();
    $edit = [
      'title[0][value]' => $node_2_title,
      'menu[enabled]' => TRUE,
      'menu[title]' => $node_2_title,
      'menu[menu_parent]' => 'main:',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    // Disable menu item.
    $menu_links = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['title' => $node_2_title]);
    $menu_link = reset($menu_links);
    $mlid = $menu_link->id();
    $this->drupalGet("admin/structure/menu/item/$mlid/edit");
    $this->submitForm(['enabled[value]' => FALSE], 'Save');

    // Add admin link that an anonymous user doesn't have access to.
    $admin_link_title = $this->randomString();
    $edit = [
      'title[0][value]' => $admin_link_title,
      'link[0][uri]' => '/admin/config/search/sitemap',
      'menu_parent' => 'main:',
    ];
    $this->drupalGet("admin/structure/menu/manage/main/add");
    $this->submitForm($edit, 'Save');

    // Assert that main menu is included in the sitemap.
    $this->drupalGet('/sitemap');
    $elements = $this->cssSelect(".sitemap-plugin--menu");
    $this->assertEquals(\count($elements), 1, 'Main menu is included.');

    // Assert that node 1 and the admin link are listed in the sitemap, but not
    // node 2.
    $this->assertSession()->linkExists($node_1_title);
    $this->assertSession()->linkExists($admin_link_title);
    $this->assertSession()->linkNotExists($node_2_title);

    // Configure module to show all menu items.
    $this->saveSitemapForm(['plugins[menu:main][settings][show_disabled]' => TRUE]);

    // Assert that both node 1 and node 2 are listed in the sitemap.
    $this->drupalGet('/sitemap');
    $this->assertSession()->linkExists($node_1_title);
    $this->assertSession()->linkExists($node_2_title);

    // Check anon user doesn't see "Inaccessible" text for the admin link.
    $this->drupalLogin($this->anonUser);
    $this->drupalGet('/sitemap');
    $this->assertSession()->linkNotExists('Inaccessible');
  }

  // @todo test menu crud
  // @todo test multiple menus
}
