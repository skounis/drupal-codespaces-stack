<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

use Drupal\Core\Url;

/**
 * Test if layout builder can be accessed.
 *
 * @group scheduler_content_moderation_integration
 *
 * @see https://www.drupal.org/project/scheduler_content_moderation_integration/issues/3048485
 */
class LayoutBuilderTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Additional modules required for this test.
   *
   * @var array
   */
  protected static $modules = ['layout_builder', 'field_ui'];

  /**
   * Tests layout builder.
   */
  public function testLayoutBuilder() {
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access content',
      'administer node display',
    ]));

    $path = 'admin/structure/types/manage/page/display/default';

    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->checkField('layout[enabled]');
    $page->pressButton('Save');

    $this->drupalGet(Url::fromRoute('layout_builder.defaults.node.view', [
      'node_type' => 'page',
      'view_mode_name' => 'default',
    ]));
    $this->assertSession()->statusCodeEquals(200);
  }

}
