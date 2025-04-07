<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Drupal\block\BlockInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * @group project_browser
 */
final class RenderElementTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait;

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
   * The project browser block entity under test.
   *
   * @var \Drupal\block\BlockInterface
   */
  private readonly BlockInterface $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['project_browser_test_mock'])
      ->save();

    $this->block = $this->drupalPlaceBlock('project_browser_block:project_browser_test_mock');
    $account = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($account);
  }

  /**
   * Tests that the render element properties are correctly defaulted.
   */
  public function testDefaults(): void {
    $this->drupalGet('<front>');
    // Pagination is enabled by default, but there aren't enough items loaded to
    // show the pager, until we clear all filters.
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();
    $this->assertPageHasText('25 Results');
    $assert_session = $this->assertSession();
    // Assert that the default pager options are there.
    $assert_session->optionExists('Items per page', '12');
    $assert_session->optionExists('Items per page', '24');
    $assert_session->optionExists('Items per page', '36');
    $assert_session->optionExists('Items per page', '48');
    $assert_session->fieldValueEquals('Items per page', '12');
    // All the sort options defined by the source should be available.
    $assert_session->optionExists('Sort by', 'Most popular');
    $assert_session->optionExists('Sort by', 'A-Z');
    $assert_session->optionExists('Sort by', 'Z-A');
    $assert_session->optionExists('Sort by', 'Newest first');
    // The default (first) sort criterion should be selected.
    $assert_session->fieldValueEquals('Sort by', 'usage_total');
    // All filters should be shown by default.
    $assert_session->fieldExists('Search');
    $assert_session->pageTextContains('Filter by category');
    $assert_session->fieldExists('security_advisory_coverage');
    $assert_session->fieldExists('maintenance_status');
    $assert_session->fieldExists('development_status');
  }

  /**
   * Tests enabling only a subset of sort options.
   */
  public function testSubsetOfSortOptions(): void {
    $this->block->set('settings', [
      'sort_options' => ['a_z', 'z_a'],
    ])->save();

    $this->drupalGet('<front>');
    $this->assertPageHasText('10 Results');
    $assert_session = $this->assertSession();
    $assert_session->fieldValueEquals('Sort by', 'a_z');
    $assert_session->optionExists('Sort by', 'A-Z');
    $assert_session->optionExists('Sort by', 'Z-A');
    $assert_session->optionNotExists('Sort by', 'Most popular');
    $assert_session->optionNotExists('Sort by', 'Newest first');
    // The projects should be using the default sort.
    $assert_session->elementsCount('css', '.pb-project', 10);
    $project_titles = $this->getSession()
      ->getPage()
      ->findAll('css', '.pb-project__title');
    $this->assertSame('9 Starts With a Higher Number', $project_titles[0]->getText());
    $this->assertSame('Astronaut Simulator', $project_titles[1]->getText());
    $this->assertSame('Cream cheese on a bagel', $project_titles[2]->getText());
  }

  /**
   * Tests setting custom labels for sort options.
   */
  public function testCustomizedSortOptionLabel(): void {
    $this->block->disable()->save();

    $this->drupalGet('/project-browser/project_browser_test_mock', [
      'query' => [
        'sort_options' => [
          'a_z' => 'Forwards',
          'z_a' => 'Backwards',
        ],
      ],
    ]);
    $this->assertElementIsVisible('named', ['field', 'Sort by']);
    $assert_session = $this->assertSession();
    $assert_session->optionExists('Sort by', 'Most popular');
    $assert_session->optionExists('Sort by', 'Forwards');
    $assert_session->optionExists('Sort by', 'Backwards');
    $assert_session->optionExists('Sort by', 'Newest first');
  }

  /**
   * Tests enabling only a single sort option.
   */
  public function testOneSortOption(): void {
    $this->block->set('settings', [
      'sort_options' => ['z_a'],
    ])->save();

    $this->drupalGet('<front>');
    $this->assertPageHasText('10 Results');
    $assert_session = $this->assertSession();
    // There are less than two sort options, so the selector should not appear.
    $this->assertEmpty($assert_session->waitForField('Sort by', 4000));
    // The projects should be using the default sort.
    $assert_session->elementsCount('css', '.pb-project', 10);
    $project_titles = $this->getSession()
      ->getPage()
      ->findAll('css', '.pb-project__title');
    $this->assertSame('Unwritten&:/', $project_titles[0]->getText());
    $this->assertSame('Pinky and the Brain', $project_titles[1]->getText());
    $this->assertSame('Octopus', $project_titles[2]->getText());
  }

  /**
   * Tests disabling pagination entirely.
   */
  public function testPaginationDisabled(): void {
    $this->block->set('settings', ['paginate' => FALSE])->save();

    $this->drupalGet('<front>');
    // Pagination is enabled by default, but there aren't enough items loaded to
    // show the pager, until we clear all filters. But even then, we shouldn't
    // see more than the first page of results.
    $this->assertElementIsVisible('named', ['button', 'Clear filters'])->press();
    $this->assertPageHasText('12 Results');
    $assert_session = $this->assertSession();
    // The paginator should never show up.
    $this->assertEmpty($assert_session->waitForField('Items per page', 4000));
    $this->assertEmpty($assert_session->waitForElementVisible('css', '.pager__items', 4000));
    // We should see 12 projects -- the default page size.
    $assert_session->elementsCount('css', '.pb-project', 12);
  }

  /**
   * Tests disabling pagination while setting a custom page size.
   */
  public function testCustomPageSizeWithPaginationDisabled(): void {
    $this->block->set('settings', [
      'paginate' => FALSE,
      'page_sizes' => '5',
    ])->save();

    $this->drupalGet('<front>');
    $this->assertPageHasText('5 Results');
    $assert_session = $this->assertSession();
    // We should see 5 projects -- the custom page size.
    $assert_session->elementsCount('css', '.pb-project', 5);
    // The paginator should never show up.
    $this->assertEmpty($assert_session->waitForField('Items per page', 4000));
    $this->assertEmpty($assert_session->waitForElementVisible('css', '.pager__items', 4000));
  }

  /**
   * Tests pagination with an immutable page size.
   */
  public function testSingleCustomPageSize(): void {
    $this->block->set('settings', ['page_sizes' => '5'])->save();

    $this->drupalGet('<front>');
    $this->assertPageHasText('10 Results');
    $assert_session = $this->assertSession();
    // We should see 5 projects -- the custom page size.
    $assert_session->elementsCount('css', '.pb-project', 5);
    // But we should see it split across two pages.
    $assert_session->elementsCount('css', '.pager__item--number', 2);
    // There should not be a way to change the page size.
    $assert_session->fieldNotExists('Items per page');
  }

  /**
   * Tests setting custom page sizes.
   */
  public function testCustomPageSizes(): void {
    $this->block->set('settings', [
      'page_sizes' => '3, 6, 9',
    ])->save();

    $this->drupalGet('<front>');
    $this->assertPageHasText('10 Results');
    $assert_session = $this->assertSession();
    // The page size selector should show up, and have our configured options.
    $this->assertElementIsVisible('named', ['field', 'Items per page']);
    $assert_session->optionExists('Items per page', '3');
    $assert_session->optionExists('Items per page', '6');
    $assert_session->optionExists('Items per page', '9');
    // The first page size should be chosen by default.
    $assert_session->fieldValueEquals('Items per page', '3');
    // The 10 results should be spread across 4 pages.
    $assert_session->elementsCount('css', '.pager__item--number', 4);
    // We should only be seeing 3 projects on this page.
    $assert_session->elementsCount('css', '.pb-project', 3);
    // Choosing a different page size should give us that many projects.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Items per page', '9');
    $this->assertTrue(
      $page->waitFor(
        10,
        fn (DocumentElement $page) => count($page->findAll('css', '.pb-project')) === 9
      ),
    );
    // We still have 10 results, and there are now only 2 pages.
    $assert_session->pageTextContains('10 Results');
    $assert_session->elementsCount('css', '.pager__item--number', 2);
  }

  /**
   * Tests using a sort criterion other than the default.
   */
  public function testNonDefaultSort(): void {
    $this->block->set('settings', ['default_sort' => 'z_a'])->save();

    $get_project_titles = function (): array {
      return array_map(
        fn (NodeElement $card): string => $card->getText(),
        $this->getSession()->getPage()->findAll('css', '.pb-project__title'),
      );
    };

    $this->drupalGet('<front>');
    $this->assertElementIsVisible('css', '.pb-project');
    $project_titles = $get_project_titles();
    $this->assertSame('Unwritten&:/', $project_titles[0]);
    $this->assertSame('Pinky and the Brain', $project_titles[1]);
    $this->assertSame('Octopus', $project_titles[2]);

    // Switch to a different page and ensure we see different projects.
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Clear filters')->press();
    $this->assertPageHasText('25 Results');
    $assert_session->elementExists('css', '.pager__item--number:nth-child(2) a')->click();
    $this->waitForProject('Jazz');
    $project_titles = $get_project_titles();
    $this->assertSame('Jazz', $project_titles[0]);
    $this->assertSame('Ice Ice', $project_titles[1]);
    $this->assertSame('Helvetica', $project_titles[2]);
    // The chosen sort should be unchanged.
    $assert_session->fieldValueEquals('Sort by', 'z_a');
  }

  /**
   * Tests that only enabled filters are displayed.
   */
  public function testFilterVisibility(): void {
    $this->block->set('settings', [
      'filters' => [
        'search',
        'security_advisory_coverage',
        'categories',
      ],
    ])->save();

    $this->drupalGet('<front>');
    $this->assertElementIsVisible('css', '.search__sort');
    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Search');
    $assert_session->fieldExists('security_advisory_coverage');
    $assert_session->pageTextContains('Filter by category');
    $assert_session->fieldNotExists('development_status');
    $assert_session->fieldNotExists('maintenance_status');
  }

  /**
   * Tests overriding default filter values.
   */
  public function testOverrideFilterValues(): void {
    $assert_session = $this->assertSession();
    $this->block->disable()->save();

    $this->drupalGet('/project-browser/project_browser_test_mock', [
      'query' => [
        'filters' => [
          'categories' => [52, 53],
          'search' => 'hear hear',
          'security_advisory_coverage' => 0,
        ],
      ],
    ]);
    $this->assertElementIsVisible('css', '.search__sort');
    $this->assertSame(['Integrations', 'Administration Tools'], $this->getSelectedCategories());
    $assert_session->fieldValueEquals('Search', 'hear hear');
    $assert_session->fieldValueEquals('security_advisory_coverage', '');
    $assert_session->fieldNotExists('development_status');
    $assert_session->fieldNotExists('maintenance_status');
  }

  /**
   * Tests disabling all filters, but keeping the default sort options.
   */
  public function testNoFilters(): void {
    $this->block->set('settings', [
      'filters' => [],
    ])->save();

    $this->drupalGet('<front>');
    $this->assertElementIsVisible('css', '.search__sort');
    $assert_session = $this->assertSession();
    // No filters should be visible...
    $assert_session->fieldNotExists('Search');
    $assert_session->fieldNotExists('security_advisory_coverage');
    $assert_session->pageTextNotContains('Filter by category');
    $assert_session->fieldNotExists('development_status');
    $assert_session->fieldNotExists('maintenance_status');
    // ...but the sorts should be.
    $assert_session->fieldExists('Sort by');
  }

  /**
   * Tests disabling all filters and sorts.
   */
  public function testNoFiltersOrSorts(): void {
    $this->block->disable()->save();

    $this->drupalGet('/project-browser/project_browser_test_mock', [
      'query' => [
        'filters' => 'none',
        'sort_options' => 'none',
      ],
    ]);
    // The whole search n' sort area should never appear.
    $this->assertEmpty($this->assertSession()->waitForElementVisible('css', '.search__sort', 4000));
  }

}
