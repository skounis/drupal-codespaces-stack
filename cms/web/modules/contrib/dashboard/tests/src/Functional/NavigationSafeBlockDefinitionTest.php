<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the definition of navigation safe blocks.
 *
 * @group navigation
 */
class NavigationSafeBlockDefinitionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation', 'block', 'dashboard'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with permission to administer navigation blocks and access navigation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user, log in and enable test navigation blocks.
    $this->adminUser = $this->drupalCreateUser([
      'configure navigation layout',
      'access navigation',
      'administer blocks',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests logic to include blocks in Navigation Layout UI.
   */
  public function testNavigationSafeBlockDefinition(): void {
    if (version_compare(\Drupal::VERSION, '11.1', '<')) {
      $this->markTestSkipped('This will only work on 11.x-dev');
    }
    // Confirm that default blocks are available.
    $layout_url = '/admin/config/user-interface/navigation-block';
    $this->drupalGet($layout_url);
    $this->clickLink('Add block');

    $this->assertSession()->linkExists('Navigation Dashboard');
  }

  /**
   * Tests logic to exclude blocks in Block Layout UI.
   */
  public function testNavigationBlocksHiddenInBlockLayout(): void {
    $block_url = '/admin/structure/block';
    $this->drupalGet($block_url);
    $this->clickLink('Place block');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/navigation_dashboard/stark');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/dashboard_site_status/stark');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/dashboard_text_block/stark');
  }

}
