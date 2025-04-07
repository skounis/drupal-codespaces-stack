<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Provides tests for the Project Browser Plugins.
 *
 * @group project_browser
 */
final class ProjectBrowserPluginTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
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
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
    ]));
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['drupal_core', 'project_browser_test_mock'])
      ->save();
  }

  /**
   * Tests paging through results.
   *
   * We want to click through things and make sure that things are functional.
   * We don't care about the number of results.
   */
  public function testPaging(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Browse core modules, because there are enough of them to paginate.
    $this->drupalGet('admin/modules/browse/drupal_core');
    // Immediately clear filters so there are enough visible to enable paging.
    $this->svelteInitHelper('test', 'Clear Filters');
    $this->svelteInitHelper('css', '.pager__item--next');
    $assert_session->elementsCount('css', '.pager__item--next', 1);

    $page->find('css', 'a[aria-label="Next page"]')?->click();
    $this->assertNotNull($assert_session->waitForElement('css', '.pager__item--previous'));
    $assert_session->elementsCount('css', '.pager__item--previous', 1);
  }

  /**
   * Tests advanced filtering.
   */
  public function testAdvancedFiltering(): void {
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Results');

    $assert_session = $this->assertSession();
    $assert_session->checkboxChecked('security_advisory_coverage');
    $assert_session->checkboxChecked('maintenance_status');
    $assert_session->checkboxNotChecked('development_status');

    $page = $this->getSession()->getPage();
    $page->uncheckField('security_advisory_coverage');
    $page->checkField('development_status');

    // Clear all filters.
    $page->pressButton('Clear filters');
    $assert_session->checkboxNotChecked('security_advisory_coverage');
    $assert_session->checkboxNotChecked('maintenance_status');
    $assert_session->checkboxNotChecked('development_status');

    // Reset to recommended filters.
    $page->pressButton('Recommended filters');
    $assert_session->checkboxChecked('security_advisory_coverage');
    $assert_session->checkboxChecked('maintenance_status');
    $assert_session->checkboxNotChecked('development_status');
  }

  /**
   * Tests broken images.
   */
  public function testBrokenImages(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', 'img[src$="images/puzzle-piece-placeholder.svg"]');

    // RandomData always give an image URL. Sometimes it is a fake URL on
    // purpose so it 404s. This check means that the original image was not
    // found and it was replaced by the placeholder.
    $assert_session->elementExists('css', 'img[src$="images/puzzle-piece-placeholder.svg"]');
  }

  /**
   * Tests the not-compatible flag.
   */
  public function testNotCompatibleText(): void {
    \Drupal::state()->set('project_browser_test_mock isCompatible', FALSE);

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.project_status-indicator');
    $this->assertEquals($this->getElementText('.project_status-indicator .visually-hidden') . ' Not compatible', $this->getElementText('.project_status-indicator'));
  }

  /**
   * Tests the detail page.
   */
  public function testDetailPage(): void {
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertElementIsVisible('css', '#project-browser .pb-project');
    $this->assertPageHasText('Results');

    $this->assertElementIsVisible('css', '.pb-project .pb-project__title .pb-project__link')
      ->click();
    $this->assertPageHasText('sites report using this module');
  }

}
