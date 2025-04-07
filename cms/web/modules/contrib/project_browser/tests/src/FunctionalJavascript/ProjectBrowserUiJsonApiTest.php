<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\project_browser\ProjectBrowser\Filter\BooleanFilter;

// cspell:ignore cashpresso Adnuntius Paypage Redsys ZURB Superfish TMGMT Toki
// cspell:ignore Webtheme Pitchburgh Gotem Webform Bsecurity Bstatus Cardless

/**
 * ProjectBrowserUITest refactored to use the Drupal.org JSON API endpoint.
 *
 * @group project_browser
 */
final class ProjectBrowserUiJsonApiTest extends WebDriverTestBase {

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
    $this->config('project_browser.admin_settings')->set('enabled_sources', ['drupalorg_jsonapi'])->save(TRUE);
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
    ]));
  }

  /**
   * Tests the display of the error message sent from Drupal.org.
   */
  public function testErrorMessageWhenWrongDrupalVersion(): void {
    // Fake the Drupal version.
    $this->container->get('state')->set('project_browser:test_deprecated_api', TRUE);

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', 'Unsupported version');
  }

  /**
   * Tests the grid view.
   */
  public function testGrid(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->getSession()->resizeWindow(1250, 1000);
    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('css', '.pb-project.pb-project--grid');
    $this->assertElementIsVisible('css', '#project-browser .pb-display__button[value="Grid"]');
    $grid_text = $this->getElementText('#project-browser .pb-display__button[value="Grid"]');
    $this->assertEquals('Grid', $grid_text);
    $this->assertPageHasText('Results');
    $assert_session->pageTextNotContains('No records available');
    $page->pressButton('List');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--list');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--list', 12);
    $page->pressButton('Grid');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--grid');
    $this->getSession()->resizeWindow(1100, 1000);
    $assert_session->assertNoElementAfterWait('css', '.toggle.list-button');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--list');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--list', 12);
    $this->getSession()->resizeWindow(1210, 1210);
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--grid');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--grid', 12);
  }

  /**
   * Tests the available categories.
   */
  public function testCategories(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('css', '.pb-filter__multi-dropdown input[type="checkbox"]');
    $assert_session->elementsCount('css', '.pb-filter__multi-dropdown input[type="checkbox"]', 19);
  }

  /**
   * Tests the clickable category functionality.
   */
  public function testClickableCategory(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', 'Token');
    $assert_session->waitForButton('Token')->click();

  }

  /**
   * Tests category filtering.
   */
  public function testCategoryFiltering(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('css', '.pb-filter__multi-dropdown');
    // Initial results count on page load.
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    // Open category drop-down.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $this->assertElementIsVisible('named', ['field', 'E-commerce']);
    $assert_session->fieldExists('E-commerce')->check();

    // Use blur event to close drop-down so Clear is visible.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->blur();

    $module_category_e_commerce_filter_selector = 'p.filter-applied:nth-child(1)';
    // Make sure the 'E-commerce' module category filter is applied.
    $this->assertEquals('E-commerce', $this->getElementText("$module_category_e_commerce_filter_selector .filter-applied__label"));
    $assert_session->pageTextContains(' Results');
    $assert_session->pageTextNotContains(' 0 Results');

    // Clear the checkbox to verify the results revert to their initial state.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $this->assertElementIsVisible('named', ['field', 'E-commerce']);
    $assert_session->fieldExists('E-commerce')->uncheck();
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->blur();

    $page->pressButton('Clear filters');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');

    // Open category drop-down.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();

    // Click 'Media' checkbox.
    $assert_session->fieldExists('Media')->check();
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');

    // Click 'Developer tools' checkbox.
    $assert_session->fieldExists('Developer tools')->check();

    // Make sure the 'Media' module category filter is applied.
    $this->assertEquals('Media', $this->getElementText('p.filter-applied:nth-child(2) .filter-applied__label'));
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
  }

  /**
   * Tests the Target blank functionality.
   */
  public function testTargetBlank(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', 'Token');
    $assert_session->waitForButton('Token')->click();
  }

  /**
   * Tests paging through results.
   */
  public function testPaging(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', ' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    $this->assertPagerItems(['1', '2', '3', '4', '5', '…', 'Next', 'Last']);

    $page->pressButton('Clear filters');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    $this->assertPagerItems(['1', '2', '3', '4', '5', '…', 'Next', 'Last']);
    $assert_session->elementExists('css', '.pager__item--active > .is-active[aria-label="Page 1"]');

    $pager = $assert_session->elementExists('css', '.pager');
    $pager->clickLink('Next');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    $this->assertPagerItems(['First', 'Previous', '1', '2', '3', '4', '5', '6', '…', 'Next', 'Last']);

    $page->clickLink('Next');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
    $this->assertPagerItems(['First', 'Previous', '1', '2', '3', '4', '5', '6', '7', '…', 'Next', 'Last']);

    // Ensure that when the number of projects is even divisible by the number
    // shown on a page, the pager has the correct number of items.
    $pager->clickLink('First');

    // Open category drop-down and select some categories.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $page->checkField('Accessibility');
    $page->checkField('E-commerce');
    $page->checkField('Media');
    $assert_session->pageTextNotContains(' 0 Results');
    $this->assertPagerItems(['1', '2', '3', '4', '5', '…', 'Next', 'Last']);
  }

  /**
   * Tests advanced filtering.
   */
  public function testAdvancedFiltering(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->waitForProject('Token');
    $page = $this->getSession()->getPage();
    $page->pressButton('Clear filters');
    $page->pressButton('Recommended filters');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');

    // Make sure the second filter applied is the security covered filter.
    $assert_session->checkboxChecked('security_advisory_coverage');

    // Clear the security covered filter.
    $page->uncheckField('security_advisory_coverage');
    $assert_session->pageTextContains(' Results');
    $assert_session->pageTextNotContains(' 0 Results');

    // Click the Active filter.
    $page->checkField('development_status');

    // Clear all filters.
    $page->pressButton('Clear filters');
    $this->assertPageHasText(' Results');

    // Click the Actively maintained filter.
    $page->checkField('maintenance_status');
    $assert_session->pageTextNotContains(' 0 Results');
    $assert_session->pageTextContains(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
  }

  /**
   * Tests sorting criteria.
   */
  public function testSortingCriteria(): void {
    $assert_session = $this->assertSession();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();

    // Select 'Recently created' option.
    $this->sortBy('created');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
  }

  /**
   * Tests search with strings that need URI encoding.
   */
  public function testSearchForSpecialChar(): void {
    $this->markTestSkipped('We are using mocks of real data from Drupal.org, what we currently have does not have content suitable for this test.');
  }

  /**
   * Tests the detail page.
   */
  public function testDetailPage(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', 'Token');
    $assert_session->waitForButton('Token')->click();
  }

  /**
   * Tests that filtering, sorting, paging persists.
   */
  public function testPersistence(): void {
    $this->markTestSkipped('Testing this with the JSON Api endpoint is not needed. The feature is not source dependent.');
  }

  /**
   * Tests recommended filters.
   */
  public function testRecommendedFilter(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();
    $this->assertPageHasText('Results');
    $page->pressButton('Recommended filters');

    // Check that the actively maintained tag is present.
    $assert_session->checkboxChecked('maintenance_status');
    // Make sure the second filter applied is the security covered filter.
    $assert_session->checkboxChecked('security_advisory_coverage');
    $this->assertPageHasText(' Results');
    $assert_session->pageTextNotContains(' 0 Results');
  }

  /**
   * Tests filters are displayed if they are defined by source.
   */
  public function testFiltersShownIfDefinedBySource(): void {
    $assert_session = $this->assertSession();
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['project_browser_test_mock'])
      ->save();

    // Make the mock source show no filters, and ensure that we never see any
    // after a brief wait.
    \Drupal::state()->set('filters_to_define', []);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertNull($assert_session->waitForElementVisible('css', '.search__filters', 4000));

    // Set the filters which will be defined by the test mock.
    // @see \Drupal\project_browser_test\Plugin\ProjectBrowserSource\ProjectBrowserTestMock::getFilterDefinitions()
    \Drupal::state()->set('filters_to_define', [
      'maintenance_status' => new BooleanFilter(
        TRUE,
        'Only show actively maintained projects',
        NULL,
      ),
      'security_advisory_coverage' => new BooleanFilter(
        TRUE,
        'Only show projects covered by a security policy',
        NULL,
      ),
    ]);
    $this->getSession()->reload();
    // Drupal.org test mock defines only two filters (actively maintained filter
    // and security coverage filter).
    $this->assertElementIsVisible('css', '.search__form-filters-container');
    $this->assertPageHasText('Show actively maintained projects');
    $assert_session->checkboxChecked('maintenance_status');
    $this->assertPageHasText('Show projects covered by a security policy');
    $assert_session->checkboxChecked('security_advisory_coverage');
    // Make sure no other filters are displayed.
    $this->assertFalse($assert_session->waitForText('Show projects under active development'));
    $this->assertNull($assert_session->waitForField('development_status'));
    $this->assertFalse($assert_session->waitForText('Filter by category'));
    // Make sure category filter element is not visible.
    $this->assertNull($assert_session->waitForElementVisible('css', 'div.search__form-filters-container > div.search__form-filters > section > fieldset > div'));
    $this->assertElementIsVisible('named', ['field', 'maintenance_status']);
    $this->assertElementIsVisible('named', ['field', 'security_advisory_coverage']);
    // Make sure no other filters are displayed after a brief wait.
    $this->assertNull($assert_session->waitForField('development_status', 4000));
    $this->assertFalse($assert_session->waitForText('Filter by category', 4000));
    // Make sure category filter element is not visible after a brief wait.
    $this->assertNull($assert_session->waitForElementVisible('css', 'div.search__form-filters-container > div.search__form-filters > section > fieldset > div', 4000));
  }

  /**
   * Tests the view mode toggle keeps its state.
   */
  public function testToggleViewState(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $viewSwitches = [
      [
        'selector' => '.pb-display__button[value="Grid"]',
        'value' => 'Grid',
      ], [
        'selector' => '.pb-display__button[value="List"]',
        'value' => 'List',
      ],
    ];
    $this->getSession()->resizeWindow(1300, 1300);

    foreach ($viewSwitches as $selector) {
      $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
      $this->svelteInitHelper('css', $selector['selector']);
      $page->pressButton($selector['value']);
      $this->svelteInitHelper('text', 'Token');
      $assert_session->waitForButton('Token')->click();
      $this->svelteInitHelper('text', 'Close');
      $assert_session->waitForButton('Close')->click();
      $assert_session->elementExists('css', $selector['selector'] . '.pb-display__button--selected');
    }
  }

}
