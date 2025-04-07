<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\block\Entity\Block;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the project browser block.
 *
 * @covers \Drupal\project_browser\Plugin\Block\ProjectBrowserBlock
 * @group project_browser
 */
final class ProjectBrowserBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'project_browser_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['project_browser_test_mock'])
      ->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
      'administer modules',
    ]));
    $this->drupalPlaceBlock('project_browser_block:project_browser_test_mock', [
      'id' => 'project_browser_test_block',
      'label' => 'Project browser block',
      'simulate_preview' => FALSE,
    ]);
  }

  /**
   * Tests the project browser block is loading.
   */
  public function testProjectBrowserBlockLoaded(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Project Browser block');
  }

  /**
   * Tests that the block only appears if the source is enabled.
   */
  public function testBlockIsBrokenIfSourceIsDisabled(): void {
    $this->drupalGet('<front>');
    $assertSession = $this->assertSession();
    $assertSession->pageTextContains('Project browser block');
    $assertSession->pageTextNotContains('This block is broken or missing.');

    // Globally disable the source, even though the block's still refers to it.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [])
      ->save();

    // The block should still appear, but it should be broken or missing.
    $this->getSession()->reload();
    $assertSession->pageTextContains('Project browser block');
    $assertSession->pageTextContains('This block is broken or missing.');
  }

  /**
   * Tests block only appears for users with administer modules permission.
   */
  public function testBlockPermissions(): void {
    $assertSession = $this->assertSession();

    // Verify user with administer modules permission can see block.
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Project browser block');

    $this->drupalLogout();
    // Verify block no longer displays.
    $this->drupalGet('<front>');
    $assertSession->pageTextNotContains('Project browser block');
  }

  /**
   * Tests that the block doesn't render the project browser in preview mode.
   */
  public function testPreviewMode(): void {
    $block = Block::load('project_browser_test_block');
    $block?->set('settings', ['simulate_preview' => TRUE])->save();

    $this->drupalGet('<front>');
    $this->assertSession()
      ->pageTextContains('Project Browser is being rendered in preview mode, so not loading projects. This block uses the Project Browser Mock Plugin source.');
  }

  /**
   * Tests the block settings form.
   */
  public function testBlockSettingsForm(): void {
    $edit_form = '/admin/structure/block/manage/project_browser_test_block';
    $this->drupalGet($edit_form);
    $assertSession = $this->assertSession();
    // Ensure the default values look the way we expect.
    $assertSession->checkboxChecked('Enable pagination');
    $assertSession->fieldValueEquals('Page sizes', '12, 24, 36, 48');
    $assertSession->fieldValueEquals('Default sort', 'usage_total');
    $assertSession->optionExists('Default sort', 'Most popular');
    $assertSession->optionExists('Default sort', 'A-Z');
    $assertSession->optionExists('Default sort', 'Z-A');
    // Ensure that all filters are enabled by default.
    $assertSession->checkboxChecked('Categories');
    $assertSession->checkboxChecked('Only show projects under active development');
    $assertSession->checkboxChecked('Search');
    $assertSession->checkboxChecked('Only show projects covered by a security policy');
    $assertSession->checkboxChecked('Only show actively maintained projects');

    $page = $this->getSession()->getPage();
    $page->uncheckField('Enable pagination');
    // Ensure that the page sizes are validated.
    $page->fillField('Page sizes', 'z 3, 10a,25b');
    $page->selectFieldOption('Default sort', 'Z-A');
    $page->pressButton('Save block');
    $assertSession->statusMessageContains('The page sizes must be a comma-separated list of numbers greater than zero.', 'error');
    $page->fillField('Page sizes', '5,0,10');
    $page->pressButton('Save block');
    $assertSession->statusMessageContains('The page sizes must be a comma-separated list of numbers greater than zero.', 'error');
    $page->fillField('Page sizes', ' 3, 10,25');
    $page->pressButton('Save block');

    // Ensure that at least one sort option is required.
    $this->drupalGet($edit_form);
    $page->uncheckField('Most popular');
    $page->uncheckField('A-Z');
    $page->uncheckField('Z-A');
    $page->uncheckField('Newest first');
    $page->pressButton('Save block');
    $assertSession->statusMessageContains('Sort options field is required.', 'error');
    $page->checkField('Newest first');
    $page->checkField('Z-A');
    // The default sort has to be one of the enabled sort options.
    $page->selectFieldOption('Default sort', 'A-Z');
    $page->pressButton('Save block');
    $assertSession->statusMessageContains('The default sort must be one of the enabled sort options.', 'error');
    $page->selectFieldOption('Default sort', 'Z-A');
    // Disable a couple of filters.
    $page->uncheckField('Categories');
    $page->uncheckField('Search');
    $page->pressButton('Save block');

    $settings = Block::load('project_browser_test_block')?->get('settings');
    $this->assertFalse($settings['paginate']);
    $this->assertSame('3, 10,25', $settings['page_sizes']);
    $this->assertSame(['z_a', 'created'], $settings['sort_options']);
    $this->assertSame('z_a', $settings['default_sort']);
    $this->assertSame(['security_advisory_coverage', 'maintenance_status', 'development_status'], $settings['filters']);

    $this->drupalGet($edit_form);
    $assertSession->checkboxNotChecked('Enable pagination');
    $assertSession->fieldValueEquals('Page sizes', '3, 10,25');
    $assertSession->checkboxNotChecked('Most popular');
    $assertSession->checkboxNotChecked('A-Z');
    $assertSession->checkboxChecked('Z-A');
    $assertSession->checkboxChecked('Newest first');
    $assertSession->fieldValueEquals('Default sort', 'z_a');
    $assertSession->checkboxChecked('Only show projects under active development');
    $assertSession->checkboxChecked('Only show actively maintained projects');
    $assertSession->checkboxChecked('Only show projects covered by a security policy');
    $assertSession->checkboxNotChecked('Search');
    $assertSession->checkboxNotChecked('Categories');
  }

}
