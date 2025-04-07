<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;

// cspell:ignore coverageall doomer eggman quiznos statusactive statusmaintained
// cspell:ignore vetica

/**
 * Provides tests for the Project Browser UI.
 *
 * These tests rely on a module that replaces Project Browser data with
 * test data.
 *
 * @see project_browser_test_install()
 *
 * @group project_browser
 */
final class ProjectBrowserUiTest extends WebDriverTestBase {

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
    $this->config('project_browser.admin_settings')->set('enabled_sources', ['project_browser_test_mock'])->save(TRUE);
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
    ]));
  }

  /**
   * Tests the grid view.
   */
  public function testGrid(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->getSession()->resizeWindow(1250, 1000);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-project.pb-project--grid');
    $this->assertNotEmpty($assert_session->waitForButton('Grid'));
    $this->svelteInitHelper('text', '10 Results');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--grid', 10);
    $this->assertPageHasText('Results');
    $assert_session->pageTextNotContains('No projects found');
    $page->pressButton('List');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--list');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--list', 10);
    $page->pressButton('Grid');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--grid');
    $this->getSession()->resizeWindow(1100, 1000);
    $assert_session->assertNoElementAfterWait('css', '.pb-display__button[value="List"]');
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--list');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--list', 10);
    $this->getSession()->resizeWindow(1210, 1210);
    $this->assertElementIsVisible('css', '#project-browser .pb-project.pb-project--grid');
    $assert_session->elementsCount('css', '#project-browser .pb-project.pb-project--grid', 10);
  }

  /**
   * Tests the available categories.
   */
  public function testCategories(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-filter__checkbox');
    $assert_session->elementsCount('css', '.pb-filter__checkbox', 19);
  }

  /**
   * Tests the clickable category functionality on module page.
   */
  public function testClickableCategory(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Dancing Queen');

    // Click to open module page.
    $assert_session->waitForButton('Dancing Queen')?->click();
  }

  /**
   * Tests category filtering.
   */
  public function testCategoryFiltering(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-filter__multi-dropdown');
    // Initial results count on page load.
    $this->assertPageHasText('10 Results');
    // Open category drop-down and select the "E-commerce" category.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $e_commerce = $this->assertElementIsVisible('named', ['field', 'E-commerce']);
    $e_commerce->check();

    // Make sure the 'E-commerce' module category filter is applied.
    $this->assertSame(['E-commerce'], $this->getSelectedCategories());

    // This call has the second argument, `$reload`, set to TRUE due to it
    // failing on ~2% of GitLabCI test runs. It is not entirely clear why this
    // specific call intermittently fails while others do not. It's known the
    // Svelte app has occasional initialization problems on GitLabCI that are
    // reliably fixed by a page reload, so we allow that here to prevent random
    // failures that are not representative of real world use.
    $this->assertProjectsVisible([
      'Cream cheese on a bagel',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Helvetica',
      'Astronaut Simulator',
    ]);

    // Clear the checkbox to verify the results revert to their initial state.
    $e_commerce->uncheck();
    $this->assertPageHasText('10 Results');

    // Use blur event to close drop-down so Clear is visible.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->blur();

    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');

    // Open category drop-down again by pressing space.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->keyDown(' ');

    // Click 'Media' checkbox.
    $assert_session->waitForField('Media')?->check();

    // Click 'E-commerce' checkbox.
    $e_commerce->check();

    // Make sure the 'Media' module category filter is applied.
    $this->assertSame(['Media', 'E-commerce'], $this->getSelectedCategories());
    // Assert that only media and administration module categories are shown.
    $this->assertProjectsVisible([
      'Jazz',
      'Eggman',
      'Tooth Fairy',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'No Scrubs',
      'Soup',
      'Mad About You',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
    ]);
    $this->assertPageHasText('20 Results');
  }

  /**
   * Tests the Target blank functionality.
   */
  public function testTargetBlank(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Helvetica');
    $assert_session->waitForButton('Helvetica')?->click();
  }

  /**
   * Tests read-only input fields for referred commands.
   */
  public function testReadonlyFields(): void {
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Helvetica');

    $this->assertElementIsVisible('named', ['button', 'View Commands for Helvetica'])
      ->press();

    $command_boxes = $page->waitFor(10, fn ($page) => $page->findAll('css', '.command-box textarea[readonly]'));
    $this->assertCount(2, $command_boxes);

    // The first textarea should have the command to require the module.
    $this->assertSame('composer require drupal/helvetica', $command_boxes[0]->getValue());
    // And the second textarea should have the command to install it.
    $value = $command_boxes[1]->getValue();
    $this->assertIsString($value);
    $this->assertStringEndsWith('drush install helvetica', $value);

    // Tests alt text for copy command image.
    $download_commands = $page->findAll('css', '.command-box img');
    $this->assertCount(2, $download_commands);
    $this->assertEquals('Copy the download command', $download_commands[0]->getAttribute('alt'));
    $this->assertIsString($download_commands[1]->getAttribute('alt'));
    $this->assertStringStartsWith('Copy the install command', $download_commands[1]->getAttribute('alt'));
  }

  /**
   * Tests paging through results.
   */
  public function testPaging(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', '10 Results');

    $this->assertProjectsVisible([
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Octopus',
      'Helvetica',
      'Unwritten&:/',
      'Grapefruit',
      'Astronaut Simulator',
    ]);
    $this->assertPagerItems([]);

    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');
    $this->assertProjectsVisible([
      'Jazz',
      'Eggman',
      'Tooth Fairy',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Ice Ice',
      'No Scrubs',
      'Soup',
      'Mad About You',
      'Dancing Queen',
      'Kangaroo',
    ]);
    $this->assertPagerItems(['1', '2', '3', 'Next', 'Last']);
    $assert_session->elementExists('css', '.pager__item--active > .is-active[aria-label="Page 1"]');

    $pager = $assert_session->elementExists('css', '.pager');
    $page->clickLink('Next');
    $this->assertProjectsVisible([
      '9 Starts With a Higher Number',
      'Quiznos',
      'Octopus',
      'Helvetica',
      '1 Starts With a Number',
      'Ruh roh',
      'Fire',
      'Looper',
      'Become a Banana',
      'Unwritten&:/',
      'Doomer',
      'Grapefruit',
    ]);
    $this->assertPagerItems(['First', 'Previous', '1', '2', '3', 'Next', 'Last']);

    $pager->clickLink('Next');
    $this->assertProjectsVisible(['Astronaut Simulator']);
    $this->assertPagerItems(['First', 'Previous', '1', '2', '3']);

    // Ensure that when the number of projects is even divisible by the number
    // shown on a page, the pager has the correct number of items.
    $pager->clickLink('First');

    // Open category drop-down and select a few.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $page->checkField('Media');
    $page->checkField('E-commerce');
    $this->assertPageHasText('18 Results');
    $this->assertPagerItems(['1', '2', 'Next', 'Last']);

    $pager->clickLink('Next');
    $this->assertPagerItems(['First', 'Previous', '1', '2']);
  }

  /**
   * Tests paging options.
   */
  public function testPagingOptions(): void {
    $page = $this->getSession()->getPage();

    $await_n_projects = function (int $count) use ($page): void {
      $this->assertTrue($page->waitFor(
        10,
        fn (DocumentElement $page) => count($page->findAll('css', '#project-browser .pb-project.pb-project--list')) === $count,
      ));
    };
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertElementIsVisible('css', '.pb-project.pb-project--list');
    $page->pressButton('Clear filters');
    $await_n_projects(12);
    $page->selectFieldOption('num-projects', '24');
    $await_n_projects(24);
  }

  /**
   * Tests advanced filtering.
   */
  public function testAdvancedFiltering(): void {
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->waitForProject('Astronaut Simulator');
    $page->pressButton('Clear filters');
    $page->pressButton('Recommended filters');
    $this->assertProjectsVisible([
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Octopus',
      'Helvetica',
      'Unwritten&:/',
      'Grapefruit',
      'Astronaut Simulator',
    ]);

    $assert_session = $this->assertSession();
    // Make sure the second filter applied is the security covered filter.
    $assert_session->checkboxChecked('security_advisory_coverage');

    // Clear the security covered filter.
    $page->uncheckField('security_advisory_coverage');
    $this->assertProjectsVisible([
      'Jazz',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Ice Ice',
      'No Scrubs',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Quiznos',
      'Octopus',
      'Helvetica',
    ]);

    // Check aria-labelledby property for advanced filter.
    foreach ($page->findAll('css', '.filters [role="group"]') as $element) {
      $this->assertSame($element->findAll('xpath', 'div')[0]->getAttribute('id'), $element->getAttribute('aria-labelledby'));
    }

    // Click the Active filter.
    $page->checkField('development_status');

    $this->assertProjectsVisible([
      'Jazz',
      'Cream cheese on a bagel',
      'Ice Ice',
      'No Scrubs',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Octopus',
      'Helvetica',
      '1 Starts With a Number',
      'Become a Banana',
      'Grapefruit',
    ]);

    // Uncheck the security filter.
    $page->uncheckField('security_advisory_coverage');
    $this->assertProjectsVisible([
      'Jazz',
      'Cream cheese on a bagel',
      'Ice Ice',
      'No Scrubs',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Octopus',
      'Helvetica',
      '1 Starts With a Number',
      'Become a Banana',
      'Grapefruit',
    ]);

    // Clear all filters.
    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');

    // Click the Actively maintained filter.
    $page->checkField('maintenance_status');
    $this->assertProjectsVisible([
      'Jazz',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Ice Ice',
      'No Scrubs',
      'Dancing Queen',
      'Kangaroo',
      '9 Starts With a Higher Number',
      'Quiznos',
      'Octopus',
      'Helvetica',
    ]);
  }

  /**
   * Tests sorting criteria.
   */
  public function testSortingCriteria(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Clear Filters');
    $page->pressButton('Clear filters');
    $assert_session->elementsCount('css', '#pb-sort option', 4);
    $this->assertEquals('Most popular', $this->getElementText('#pb-sort option:nth-child(1)'));
    $this->assertEquals('A-Z', $this->getElementText('#pb-sort option:nth-child(2)'));
    $this->assertEquals('Z-A', $this->getElementText('#pb-sort option:nth-child(3)'));
    $this->assertEquals('Newest first', $this->getElementText('#pb-sort option:nth-child(4)'));

    // Select 'A-Z' sorting order.
    $page->selectFieldOption('Sort by', 'A-Z');

    // Assert that the projects are listed in ascending order of their titles.
    $this->assertProjectsVisible([
      '1 Starts With a Number',
      '9 Starts With a Higher Number',
      'Astronaut Simulator',
      'Become a Banana',
      'Cream cheese on a bagel',
      'Dancing Queen',
      'Doomer',
      'Eggman',
      'Fire',
      'Grapefruit',
      'Helvetica',
      'Ice Ice',
    ], in_order: TRUE);

    // Select 'Z-A' sorting order.
    $page->selectFieldOption('Sort by', 'Z-A');

    $this->assertProjectsVisible([
      'Vitamin&C;$?',
      'Unwritten&:/',
      'Tooth Fairy',
      'Soup',
      'Ruh roh',
      'Quiznos',
      'Pinky and the Brain',
      'Octopus',
      'No Scrubs',
      'Mad About You',
      'Looper',
      'Kangaroo',
    ], in_order: TRUE);

    // Select 'Active installs' option.
    $page->selectFieldOption('Sort by', 'Most popular');

    // Assert that the projects are listed in descending order of their usage.
    $this->assertProjectsVisible([
      'Jazz',
      'Eggman',
      'Tooth Fairy',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Ice Ice',
      'No Scrubs',
      'Soup',
      'Mad About You',
      'Dancing Queen',
      'Kangaroo',
    ], in_order: TRUE);

    // Select 'Newest First' option.
    $page->selectFieldOption('Sort by', 'Newest first');

    // Assert that the projects are listed in descending order of their date of
    // creation.
    $this->assertProjectsVisible([
      '9 Starts With a Higher Number',
      'Helvetica',
      'Become a Banana',
      'Ice Ice',
      'Astronaut Simulator',
      'Grapefruit',
      'Fire',
      'Cream cheese on a bagel',
      'No Scrubs',
      'Soup',
      'Octopus',
      'Tooth Fairy',
    ], in_order: TRUE);
  }

  /**
   * Tests search with strings that need URI encoding.
   */
  public function testSearchForSpecialChar(): void {
    $page = $this->getSession()->getPage();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertPageHasText('10 Results');
    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');

    // Fill in the search field.
    $this->inputSearchField('', TRUE);
    $this->inputSearchField('&', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Vitamin&C;$?',
      'Unwritten&:/',
    ]);

    // Fill in the search field.
    $this->inputSearchField('', TRUE);
    $this->inputSearchField('n&', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Vitamin&C;$?',
      'Unwritten&:/',
    ]);

    $this->inputSearchField('', TRUE);
    $this->inputSearchField('$', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Vitamin&C;$?',
    ]);

    $this->inputSearchField('', TRUE);
    $this->inputSearchField('?', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Vitamin&C;$?',
    ]);

    $this->inputSearchField('', TRUE);
    $this->inputSearchField('&:', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Unwritten&:/',
    ]);

    $this->inputSearchField('', TRUE);
    $this->inputSearchField('$?', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Vitamin&C;$?',
    ]);
  }

  /**
   * Tests the detail page.
   */
  public function testDetailPage(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Helvetica');
    $assert_session->waitForButton('Helvetica')?->click();
    // Check the detail modal displays.
    $this->assertElementIsVisible('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
    $assert_session->elementExists('css', 'button.pb__action_button');
    // Close the modal.
    $assert_session->waitForButton('Close')?->click();
    $assert_session->elementNotExists('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
  }

  /**
   * Tests the detail page.
   */
  public function testReopenDetailModal(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Helvetica');
    $assert_session->waitForButton('Helvetica')?->click();
    // Check the detail modal displays.
    $this->assertElementIsVisible('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
    $assert_session->elementExists('css', 'button.pb__action_button');
    // Close the modal and check it no longer exists.
    $assert_session->waitForButton('Close')?->click();
    $assert_session->elementNotExists('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
    // Check that a different module modal can be opened.
    $assert_session->waitForButton('Octopus')?->click();
    $this->assertElementIsVisible('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Octopus"]');
    $assert_session->waitForButton('Close')?->click();
    $assert_session->elementNotExists('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Octopus"]');
    // Check that first detail modal can be reopened.
    $assert_session->waitForButton('Helvetica')?->click();
    $this->assertElementIsVisible('xpath', '//span[contains(@class, "ui-dialog-title") and text()="Helvetica"]');
    $assert_session->elementExists('css', 'button.pb__action_button');
  }

  /**
   * Tests recommended filters.
   */
  public function testRecommendedFilter(): void {
    $page = $this->getSession()->getPage();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();
    $this->assertPageHasText('25 Results');
    $page->pressButton('Recommended filters');

    $assert_session = $this->assertSession();
    // Check that the actively maintained tag is present.
    $assert_session->checkboxChecked('maintenance_status');
    // Make sure the second filter applied is the security covered filter.
    $assert_session->checkboxChecked('security_advisory_coverage');
    $this->assertPageHasText('10 Results');
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
      $this->drupalGet('admin/modules/browse/project_browser_test_mock');
      $this->svelteInitHelper('css', $selector['selector']);
      $page->pressButton($selector['value']);
      $this->svelteInitHelper('text', 'Helvetica');
      $assert_session->waitForButton('Helvetica')?->click();
      $this->svelteInitHelper('text', 'Close');
      $assert_session->waitForButton('Close')?->click();
      $assert_session->elementExists('css', $selector['selector'] . '.pb-display__button--selected');
    }
  }

  /**
   * Tests tabledrag on configuration page.
   */
  public function testTabledrag(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->container->get('module_installer')->install(['block']);
    $this->drupalPlaceBlock('local_tasks_block');

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['drupalorg_jsonapi', 'drupal_core'])
      ->save();

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $local_tasks = $assert_session->elementExists('css', 'h2:contains("Primary tabs") + ul')
      ->findAll('css', 'li a[href*="/admin/modules/browse/"]');
    $this->assertCount(2, $local_tasks);
    // Verify that the contrib modules source is first tab.
    $this->assertSame('Contrib modules', $local_tasks[0]->getText());

    // Re-order plugins.
    $this->drupalGet('admin/config/development/project_browser');
    $first_plugin = $page->find('css', '#source--drupalorg_jsonapi');
    $second_plugin = $page->find('css', '#source--drupal_core');
    $this->assertNotNull($second_plugin);
    $first_plugin?->find('css', '.tabledrag-handle')?->dragTo($second_plugin);
    $this->assertNotNull($first_plugin);
    $this->assertTableRowWasDragged($first_plugin);
    $this->submitForm([], 'Save');

    // Verify that core modules is first tab.
    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->assertElementIsVisible('css', '#project-browser .pb-project');
    $this->assertSame('Core modules', $local_tasks[0]->getText());

    // Disable the contrib modules plugin.
    $this->drupalGet('admin/config/development/project_browser');
    $enabled_row = $page->find('css', '#source--drupalorg_jsonapi');
    $disabled_region_row = $page->find('css', '.status-title-disabled');
    $this->assertNotNull($disabled_region_row);
    $enabled_row?->find('css', '.handle')?->dragTo($disabled_region_row);
    $this->assertNotNull($enabled_row);
    $this->assertTableRowWasDragged($enabled_row);
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Verify that only core modules plugin is enabled.
    $this->drupalGet('admin/modules/browse/drupal_core');
    $this->assertElementIsVisible('css', '.pb-project');

    $this->config('project_browser.admin_settings')->set('enabled_sources', ['project_browser_test_mock'])->save(TRUE);
    $this->drupalGet('admin/config/development/project_browser');
    $this->assertTrue($assert_session->optionExists('edit-enabled-sources-project-browser-test-mock-status', 'enabled')->isSelected());
    $this->assertTrue($assert_session->optionExists('edit-enabled-sources-drupal-core-status', 'disabled')->isSelected());

    // Verify that only the mock plugin is enabled.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-filter__multi-dropdown input[type="checkbox"]');
    $assert_session->elementsCount('css', '.pb-filter__multi-dropdown input[type="checkbox"]', 19);
  }

  /**
   * Tests the visibility of categories in list and grid view.
   *
   * @testWith ["Grid"]
   *           ["List"]
   */
  public function testCategoriesVisibility(string $display_type): void {
    $this->getSession()->resizeWindow(1300, 1300);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertSession()->waitForButton($display_type)?->press();

    $helvetica = $this->waitForProject('Helvetica');
    $this->assertSame('E-commerce', $helvetica->find('css', '.pb-project-categories ul li')?->getText());

    $astronaut_simulator_categories = $this->waitForProject('Astronaut Simulator')
      ->findAll('css', '.pb-project-categories ul li');
    $this->assertCount(2, $astronaut_simulator_categories);
    $this->assertSame('E-commerce', $astronaut_simulator_categories[1]->getText());
  }

  /**
   * Tests the pagination and filtering.
   */
  public function testPaginationWithFilters(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $page->pressButton('Clear filters');
    $this->assertProjectsVisible([
      'Jazz',
      'Eggman',
      'Tooth Fairy',
      'Vitamin&C;$?',
      'Cream cheese on a bagel',
      'Pinky and the Brain',
      'Ice Ice',
      'No Scrubs',
      'Soup',
      'Mad About You',
      'Dancing Queen',
      'Kangaroo',
    ]);

    $this->assertPagerItems(['1', '2', '3', 'Next', 'Last']);
    $assert_session->elementExists('css', '.pager')->clickLink('Last');
    $this->assertProjectsVisible(['Astronaut Simulator']);

    // Open category drop-down and select the Media category.
    $assert_session->elementExists('css', '.pb-filter__multi-dropdown')->click();
    $this->assertElementIsVisible('named', ['field', 'Media'])->check();
    $assert_session->waitForText('Jazz');
    $this->assertPagerItems(['1', '2', 'Next', 'Last']);
    $assert_session->elementExists('css', '.pager__item--active > .is-active[aria-label="Page 1"]');
  }

  /**
   * Tests install button link.
   */
  public function testInstallButtonLink(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['drupal_core'])
      ->save(TRUE);
    $this->drupalGet('admin/modules/browse/drupal_core');
    $this->svelteInitHelper('css', '.pb-project.pb-project--list');

    $this->inputSearchField('inline form errors', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->svelteInitHelper('text', 'Inline Form Errors');

    $install_link = $page->find('css', '.pb-layout__main .pb-actions a');

    $href = $install_link?->getAttribute('href');
    $this->assertIsString($href);
    $this->assertStringEndsWith('/admin/modules#module-inline-form-errors', $href);
    $this->drupalGet($href);
    $this->assertElementIsVisible('css', "#edit-modules-inline-form-errors-enable");
    $assert_session->assertVisibleInViewport('css', '#edit-modules-inline-form-errors-enable');
  }

  /**
   * Confirms UI install can not be enabled without Package Manager installed.
   */
  public function testUiInstallNeedsPackageManager(): void {
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/config/development/project_browser');
    $ui_install_input = $page->find('css', '[data-drupal-selector="edit-allow-ui-install"]');
    $this->assertTrue($ui_install_input?->getAttribute('disabled') === 'disabled');

    // @todo Remove try/catch in https://www.drupal.org/i/3349193.
    try {
      $this->container->get('module_installer')->install(['package_manager']);
    }
    catch (MissingDependencyException $e) {
      $this->markTestSkipped($e->getMessage());
    }
    $this->drupalGet('admin/config/development/project_browser');
    $ui_install_input = $page->find('css', '[data-drupal-selector="edit-allow-ui-install"]');
    $this->assertFalse($ui_install_input?->hasAttribute('disabled'));
  }

  /**
   * Tests that we can clear search results with one click.
   */
  public function testClearKeywordSearch(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-search-results');

    // Get the original result count.
    $results = $assert_session->elementExists('css', '.pb-search-results');
    $this->assertTrue($results->waitFor(10, fn (NodeElement $element) => str_contains($element->getText(), 'Results')));
    $original_text = $results->getText();

    // Search for something to change it.
    $this->inputSearchField('abcdefghijklmnop', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    // Assert that the correct message is displayed when no project is present.
    $assert_session->waitForText('No projects found');
    $this->assertTrue($results->waitFor(10, fn (NodeElement $element) => $element->getText() !== $original_text));

    // Remove the search text and make sure it auto-updates.
    // Use our clear search button to do it.
    $assert_session->elementExists('css', '.search__search-clear')->click();
    $this->assertTrue($results->waitFor(10, fn (NodeElement $element) => $element->getText() === $original_text));
  }

  /**
   * Test that the clear search link is not in the tab-index.
   *
   * @see https://www.drupal.org/project/project_browser/issues/3446109
   */
  public function testSearchClearNoTabIndex(): void {
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-search-results');

    // Search and confirm clear button has no focus after tabbing.
    $this->inputSearchField('abcdefghijklmnop', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();

    $this->getSession()->getDriver()->keyPress($page->getXpath(), '9');
    $has_focus_id = $this->getSession()->evaluateScript('document.activeElement.id');
    $this->assertNotEquals('clear-text', $has_focus_id);
  }

  /**
   * Tests that recipes show instructions for applying them.
   */
  public function testRecipeInstructions(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['recipes'])
      ->save();

    $this->drupalGet('admin/modules/browse/recipes');
    $this->svelteInitHelper('css', '.pb-projects-list');
    $this->inputSearchField('image', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();

    // Look for a recipe that ships with core.
    $this->assertElementIsVisible('css', '.pb-project:contains("Image media type")')
      ->pressButton('View Commands');
    $command = $this->assertElementIsVisible('css', '.command-box textarea')
      ->getValue();
    assert(is_string($command));
    // A full path to the PHP executable should be in the command.
    $this->assertMatchesRegularExpression('/[^\s]+\/php /', $command);
    $drupal_root = $this->getDrupalRoot();
    $this->assertStringStartsWith("cd $drupal_root\n", $command);
    $this->assertStringEndsWith("php $drupal_root/core/scripts/drupal recipe $drupal_root/core/recipes/image_media_type", $command);
  }

  /**
   * Test that items with 0 active installs don't show, and >0 do.
   */
  public function testActiveInstallVisibility(): void {
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('css', '.pb-search-results');

    $this->assertElementIsVisible('css', '.pb-project');
    // Find the first and last .pb-project elements.
    $projects = $page->findAll('css', '.pb-project');

    // Assert that there are pb-project elements on the page.
    $this->assertNotEmpty($projects, 'No .pb-project elements found on the page.');

    // Check the first project DOES contain the
    // pb-project__install-count_container div.
    $first_project = reset($projects);
    $first_install_count_container = $first_project->find('css', '.pb-project__install-count-container');
    $this->assertNotNull($first_install_count_container, 'First project does not contain the install count container.');

    // Check the last project does NOT contain the
    // pb-project__install-count_container div.
    $last_project = end($projects);
    $last_install_count_container = $last_project->find('css', '.pb-project__install-count-container');
    $this->assertNull($last_install_count_container, 'Last project contains the install count container, but it should not.');
  }

  /**
   * Verifies that the wrench icon is displayed only on maintained projects.
   */
  public function testWrenchIcon(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->getSession()->resizeWindow(1460, 960);
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->svelteInitHelper('text', 'Helvetica');
    // This asserts that status icon is present on the cards.
    $this->assertElementIsVisible('css', '.pb-project__maintenance-icon .pb-project__status-icon-btn');
    $assert_session->waitForButton('Helvetica')?->click();
    $this->assertPageHasText('The module is actively maintained by the maintainers');
    // This asserts that status icon is present in detail's modal.
    $this->assertElementIsVisible('css', '.pb-detail-modal__sidebar .pb-project__status-icon-btn');
    $page->find('css', '.ui-dialog-titlebar-close')?->click();

    $page->uncheckField('maintenance_status');
    // Asserts that the text followed by status icon is missing.
    $assert_session->waitForButton('Eggman')?->click();
    $this->assertFalse($assert_session->waitForText('The module is actively maintained by the maintainers'));
  }

  /**
   * Tests that count of installs is formatted for plurals correctly.
   */
  public function testInstallCountPluralFormatting(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/modules/browse/project_browser_test_mock');

    // Ensure the project list is loaded.
    $this->assertElementIsVisible('css', '#project-browser .pb-project');
    $this->assertPageHasText('Results');

    // Expect Grapefruit to have 1 install.
    $this->assertElementIsVisible('xpath', '//span[contains(@class, "pb-project__install-count") and text()="1 install"]');

    // Locate and click the Grapefruit project link.
    $grapefruit_link = $page->find('xpath', '//button[contains(@class, "pb-project__link") and contains(text(), "Grapefruit")]');
    $grapefruit_link?->click();

    // Verify the text for Grapefruit (singular case).
    $this->assertPageHasText('site reports using this module');

    // Go back to the project list.
    $close_button = $page->find('xpath', '//button[contains(@class, "ui-dialog-titlebar-close") and contains(text(), "Close")]');
    $close_button?->click();

    // Expect Octopus to have 235 installs.
    $assert_session->elementExists('xpath', '//span[contains(@class, "pb-project__install-count") and text()="235 installs"]');

    // Locate and click the Octopus project link.
    $octopus_link = $page->find('xpath', '//button[contains(@class, "pb-project__link") and contains(text(), "Octopus")]');
    $octopus_link?->click();

    // Verify the text for Octopus (plural case).
    $this->assertPageHasText('sites report using this module');
  }

  /**
   * Tests that pressing Enter in the search box doesn't reload the page.
   */
  public function testEnterDoesNotReloadThePage(): void {
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $assert_session = $this->assertSession();
    $search_box = $this->assertElementIsVisible('css', '#pb-text');
    $this->getSession()
      ->executeScript('document.body.classList.add("same-page")');
    // Enter some nonsense in the search box and press Enter ("\r\n" in PHP).
    $search_box->focus();
    $search_box->setValue("foo\r\n");
    // The window should not have been reloaded, so the body should still have
    // the class we set.
    $assert_session->elementAttributeContains('css', 'body', 'class', 'same-page');
  }

  /**
   * Tests the singular and plural formatting for search results.
   */
  public function testSingularAndPluralResults(): void {
    // Load the Project Browser mock page.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');

    // Check for plural results initially.
    $this->svelteInitHelper('text', '10 Results');

    // Locate the search box and verify it is visible.
    $this->assertElementIsVisible('css', '#pb-text');

    // Fill in the search field.
    $this->inputSearchField('', TRUE);
    // Set the search term to "Astronaut Simulator" to narrow the results.
    $this->inputSearchField('Astronaut Simulator', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();

    // Verify the singular result count text is displayed correctly.
    $result_count = $this->assertElementIsVisible('css', '.pb-search-results');
    $this->assertTrue(
      $result_count->waitFor(
        10,
        fn (NodeElement $element) => $element->getText() === '1 Result',
      ),
    );
  }

  /**
   * Tests clicking the X next to search, or clear filters resets search.
   */
  public function testClearSearch(): void {
    $page = $this->getSession()->getPage();

    // Clear filters.
    $this->drupalGet('admin/modules/browse/project_browser_test_mock');
    $this->assertPageHasText('10 Results');
    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');

    // Fill in the search field.
    $search_field = $this->assertElementIsVisible('css', '#pb-text');
    $this->inputSearchField('Tooth Fairy', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Tooth Fairy',
    ]);
    // Check search box still has search text.
    $this->assertEquals($search_field->getValue(), 'Tooth Fairy');

    // Click "X" button and make sure search now empty.
    $this->assertElementIsVisible('css', '#clear-text')->press();
    $this->assertPageHasText('25 Results');
    $this->assertEquals($search_field->getValue(), '');

    // Run search again.
    $this->inputSearchField('Tooth Fairy', TRUE);
    $this->assertElementIsVisible('css', ".search__search-submit")->click();
    $this->assertProjectsVisible([
      'Tooth Fairy',
    ]);

    // Click Clear Filters button and make sure search empty again.
    $page->pressButton('Clear filters');
    $this->assertPageHasText('25 Results');
    $this->assertEquals($search_field->getValue(), '');

    // Ensure that clearing filters will preserve the result count, even if
    // all the initial filter values are falsy.
    \Drupal::state()->set('filters_to_define', [
      'search' => new TextFilter('', 'Search', NULL),
    ]);
    $this->getSession()->reload();
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();
    // Ensure that the result count remains even after we wait a sec for
    // Svelte to re-render.
    sleep(1);
    $this->assertElementIsVisible('css', '.pb-search-results');
  }

  /**
   * Asserts that a given list of project titles are visible on the page.
   *
   * @param array $project_titles
   *   An array of expected titles.
   * @param int $timeout
   *   (optional) How many seconds to wait before giving up. Defaults to 10.
   * @param bool $in_order
   *   (optional) If TRUE, assert that the projects are visible on the page
   *   in the same order as $project_titles. Defaults to FALSE.
   */
  private function assertProjectsVisible(array $project_titles, int $timeout = 10, bool $in_order = FALSE): void {
    $page = $this->getSession()->getPage();

    $list_visible_projects = function () use ($page): array {
      return array_map(
        fn (NodeElement $element) => $element->getText(),
        $page->findAll('css', '#project-browser .pb-project h3 button'),
      );
    };

    $missing = [];
    $success = $this->getSession()
      ->getPage()
      ->waitFor($timeout, function () use ($project_titles, &$missing, $list_visible_projects): bool {
        $missing = array_diff($project_titles, $list_visible_projects());
        return empty($missing);
      });

    $this->assertTrue(
      $success,
      sprintf('The following projects should have appeared, but did not: %s', implode(', ', $missing)),
    );
    if ($in_order) {
      $this->assertSame($project_titles, $list_visible_projects());
    }
  }

}
