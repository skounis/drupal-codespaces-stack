<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\project_browser_test\TestActivator;

/**
 * Tests multiple Project Browser instances on a single page.
 *
 * @group project_browser
 */
final class MultipleInstancesTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager',
    'project_browser_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The project browser instances on the page.
   *
   * @var \Behat\Mink\Element\NodeElement[]
   */
  private readonly array $instances;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('allow_ui_install', TRUE)
      ->set('max_selections', 2)
      ->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
    ]));

    // Only allow this specific project to be activated, because the test
    // activator will treat it as present, which means Project Browser won't try
    // to use Package Manager to set up a sandbox.
    // @see \Drupal\project_browser_test\TestActivator::getStatus()
    TestActivator::handle('drupal/pinky_brain');

    $number_of_instances = 2;
    $this->drupalGet('/project-browser/project_browser_test_mock', [
      'query' => [
        'instances' => $number_of_instances,
      ],
    ]);

    $page = $this->getSession()->getPage();
    $instances_are_loaded = $page->waitFor(
      10,
      fn (DocumentElement $page): bool => count($page->findAll('css', '.pb-projects-list')) === $number_of_instances,
    );
    $this->assertTrue($instances_are_loaded);
    $this->instances = $page->findAll('css', '[data-project-browser-instance-id]');
  }

  /**
   * Tests that multiple instances of Project Browser load on a single page.
   */
  public function testMultipleInstancesLoading(): void {
    $this->assertSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'Both instances have the same project titles in the same order.'
    );
  }

  /**
   * Tests that sorting functions independently across instances.
   */
  public function testIndependentSorting(): void {
    $this->instances[0]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[0], '25 Results');
    $this->instances[0]->selectFieldOption('Sort by', 'A-Z');
    $this->waitForElementToContainText($this->instances[0], '1 Starts With a Number');

    $this->instances[1]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[1], '25 Results');
    $this->instances[1]->selectFieldOption('Sort by', 'Z-A');
    $this->waitForElementToContainText($this->instances[1], 'Tooth Fairy');

    $this->assertNotSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'The two instances display projects in different order after applying different sorts.',
    );
  }

  /**
   * Tests max selection limit across multiple browsers.
   */
  public function testMaxSelectionLimit(): void {
    // Select first two projects in the first instance.
    $this->selectProject($this->instances[0], 0);
    $this->selectProject($this->instances[0], 1);
    $this->waitForElementToContainText($this->instances[0], '2 projects selected');

    // Try to select a third project - it should be disabled.
    $this->assertProjectCannotBeSelected($this->instances[0], 2);
    $this->deselectAllProjects($this->instances[0]);

    // Select two projects in the second instance.
    $this->selectProject($this->instances[1], 0);
    $this->selectProject($this->instances[1], 1);
    $this->waitForElementToContainText($this->instances[1], '2 projects selected');

    // Try to select a third project - it should be disabled.
    $this->assertProjectCannotBeSelected($this->instances[1], 2);
    $this->deselectAllProjects($this->instances[1]);

    // Select one project from each instance and ensure the selection limit
    // covers both of them.
    $this->selectProject($this->instances[0], 0);
    $this->selectProject($this->instances[1], 1);
    $this->assertProjectCannotBeSelected($this->instances[0], 2);
    $this->assertProjectCannotBeSelected($this->instances[1], 3);
  }

  /**
   * Tests that activating a project in one instance reflects in the other.
   */
  public function testProjectActivationSynchronization(): void {
    $this->instances[0]->pressButton('Select Pinky and the Brain');
    $this->instances[0]->pressButton('Install selected projects');
    $this->waitForElementToContainText($this->instances[0], 'Pinky and the Brain is Installed');
    $this->waitForElementToContainText($this->instances[1], 'Pinky and the Brain is Installed');
  }

  /**
   * Tests that filters apply independently to each instance.
   *
   * @param string $filter_name
   *   The human-readable name of the specific filter to test.
   * @param bool $filter_applied
   *   Whether the filter is applied or not.
   * @param int $expected_count
   *   The expected number of results that should be displayed after filtering.
   *
   * @testWith ["security_advisory_coverage", false, 17]
   *   ["maintenance_status", false, 14]
   *   ["development_status", true, 8]
   */
  public function testFiltersAreIndependent(string $filter_name, bool $filter_applied, int $expected_count): void {
    $count_results = function (NodeElement $instance): string {
      return trim($instance->find('css', '.pb-search-results')?->getText() ?? '');
    };

    // Result count is same initially.
    $this->assertSame($count_results($this->instances[0]), $count_results($this->instances[1]));

    $expected_count = "$expected_count Results";
    // Apply filter on first instance and check that count is different.
    $filter_applied
      ? $this->instances[0]->checkField($filter_name)
      : $this->instances[0]->uncheckField($filter_name);
    $this->waitForElementToContainText($this->instances[0], $expected_count);
    $this->assertNotSame($count_results($this->instances[0]), $count_results($this->instances[1]));

    // Apply same filter on second instance and check that count is same.
    $filter_applied
      ? $this->instances[1]->checkField($filter_name)
      : $this->instances[1]->uncheckField($filter_name);
    $this->waitForElementToContainText($this->instances[1], $expected_count);
    $this->assertSame($count_results($this->instances[0]), $count_results($this->instances[1]));

    // Test search filter.
    $this->instances[0]->find('css', '#pb-text')?->setValue('Dancing');
    $this->instances[0]->find('css', '.search__search-submit')?->click();
    $this->waitForElementToContainText($this->instances[0], '1 Result');
    $this->assertNotSame($count_results($this->instances[0]), $count_results($this->instances[1]));
  }

  /**
   * Tests that category filters apply independently to each instance.
   */
  public function testCategoriesFilterIsIndependent(): void {
    // Ensure that clicking a category's label (as opposed to checking the box)
    // checks the box in the correct instance.
    $this->assertSession()
      ->elementExists('css', '.pb-filter__multi-dropdown__label', $this->instances[1])
      ->click();
    $label = $this->instances[1]->findField('E-commerce')?->getParent();
    $this->assertNotEmpty($label);
    $this->assertSame('label', $label->getTagName());
    $label->click();
    $this->waitForElementToContainText($this->instances[1], '1 category selected');
    $this->assertTrue($label->find('css', 'input')?->isChecked());

    $this->instances[0]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[0], '25 Results');
    $this->instances[1]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[1], '25 Results');
    $this->assertSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'Both instances have the same project titles initially.'
    );

    // Apply category filter to the first instance.
    $this->selectCategories($this->instances[0], 'E-commerce');
    $this->waitForElementToContainText($this->instances[0], '14 Results');
    $this->assertNotSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'Instances show different projects after applying category filter to first instance.'
    );
    // Apply same category filter to the second instance.
    $this->selectCategories($this->instances[1], 'E-commerce');
    $this->waitForElementToContainText($this->instances[1], '14 Results');
    $this->assertSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'Instances show same projects after applying category filter to second instance.'
    );
    // Add another category to second instance.
    $this->selectCategories($this->instances[1], 'Integrations');
    $this->waitForElementToContainText($this->instances[1], '15 Results');
    $this->assertNotSame(
      $this->getProjectNames($this->instances[0]),
      $this->getProjectNames($this->instances[1]),
      'Instances show different projects after applying category filter to second instance.'
    );
  }

  /**
   * Gets the project names visible in a specific instance.
   *
   * @param \Behat\Mink\Element\NodeElement $instance
   *   The root element of the instance.
   *
   * @return string[]
   *   The names of the projects visible in the instance, in the order that
   *   they appear.
   */
  private function getProjectNames(NodeElement $instance): array {
    return $this->getTextOfAll($instance, '.pb-project__title button');
  }

  /**
   * Selects a project in the specified instance.
   *
   * @param \Behat\Mink\Element\NodeElement $instance
   *   The root element of the instance.
   * @param int $project_index
   *   The index of the project to select (0-based).
   */
  private function selectProject(NodeElement $instance, int $project_index): void {
    $buttons = $instance->findAll('css', ".pb__action_button");
    $this->assertLessThan(count($buttons), $project_index);
    $buttons[$project_index]->press();
  }

  /**
   * Asserts that a project's select button is disabled.
   *
   * @param \Behat\Mink\Element\NodeElement $instance
   *   The root element of the instance.
   * @param int $project_index
   *   The index of the project to check (0-based).
   */
  private function assertProjectCannotBeSelected(NodeElement $instance, int $project_index): void {
    $buttons = $instance->findAll('css', ".pb__action_button");
    $this->assertLessThan(count($buttons), $project_index);
    $this->assertTrue($buttons[$project_index]->hasAttribute('disabled'));
  }

  /**
   * Deselects all projects in the specified instance.
   *
   * @param \Behat\Mink\Element\NodeElement $instance
   *   The root element of the instance.
   */
  private function deselectAllProjects(NodeElement $instance): void {
    foreach ($instance->findAll('css', '.select_button') as $button) {
      $button_text = trim($button->getText());
      if (str_starts_with($button_text, 'Deselect ')) {
        $button->press();
      }
    }
  }

  /**
   * Selects a set of categories to filter a project browser instance by.
   *
   * @param \Behat\Mink\Element\NodeElement $instance
   *   The root element of the instance.
   * @param string ...$categories
   *   The human-readable labels of the categories to select.
   */
  private function selectCategories(NodeElement $instance, string ...$categories): void {
    if (!$instance->find('css', '.pb-filter__multi-dropdown__items')?->isVisible()) {
      $instance->find('css', '.pb-filter__multi-dropdown__label')?->click();
    }

    foreach ($categories as $category) {
      $checkbox = $instance->waitFor(10, fn (NodeElement $instance) => $instance->findField($category));
      $this->assertNotEmpty($checkbox, "Category '$category' wasn't found.");
      $checkbox->check();
    }
  }

  /**
   * Tests pagination functionality across multiple Project Browser instances.
   */
  public function testIndependentPagination(): void {
    $assert_session = $this->assertSession();

    // Verify no pagination exists initially, because the default filters don't
    // yield enough results to paginate.
    $assert_session->elementNotExists('css', '.pager', $this->instances[0]);
    $assert_session->elementNotExists('css', '.pager', $this->instances[1]);

    // Clear filters on the first instance and assert that it gets paginated,
    // but the other instance doesn't.
    $this->instances[0]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[0], '25 Results');
    $pager = $assert_session->elementExists('css', '.pager', $this->instances[0]);
    $this->assertSame(['1', '2', '3', 'Next', 'Last'], $this->getTextOfAll($pager, 'a'));
    $assert_session->elementNotExists('css', '.pager', $this->instances[1]);

    // Navigate to second page on first instance and verify that it shows
    // different projects than the other instance.
    $first_instance_initial_names = $this->getProjectNames($this->instances[0]);
    $second_instance_initial_names = $this->getProjectNames($this->instances[1]);

    $pager->clickLink('Next');
    $this->waitForElementToContainText($this->instances[0], '9 Starts With a Higher Number');

    $first_instance_second_page_names = $this->getProjectNames($this->instances[0]);
    $this->assertNotSame(
      $first_instance_initial_names,
      $first_instance_second_page_names,
      'Project list changes on second page of first instance.'
    );
    $this->assertNotSame(
      $second_instance_initial_names,
      $first_instance_second_page_names,
      'Second page of first instance differs from initial list of second instance.'
    );

    // Clear filters on the second instance and assert that it gets paginated.
    // Then navigate to the second page of the second instance and ensure that
    // it shows the same projects that the first instance's second page does.
    $this->instances[1]->pressButton('Clear filters');
    $this->waitForElementToContainText($this->instances[1], '25 Results');
    $pager = $assert_session->elementExists('css', '.pager', $this->instances[1]);
    $this->assertSame(['1', '2', '3', 'Next', 'Last'], $this->getTextOfAll($pager, 'a'));
    $pager->clickLink('Next');
    $this->waitForElementToContainText($this->instances[1], '9 Starts With a Higher Number');

    $this->assertSame(
      $first_instance_second_page_names,
      $this->getProjectNames($this->instances[1]),
      'Project list on second page is identical across instances.'
    );
    // Go to last page on second instance and verify it shows different projects
    // than the first instance.
    $pager->clickLink('Last');
    $this->waitForElementToContainText($this->instances[1], 'Astronaut Simulator');
    $this->assertNotSame(
      $first_instance_second_page_names,
      $this->getProjectNames($this->instances[1]),
      'Last page project list is different from second page of first instance.'
    );
  }

  /**
   * Gets the text of all child elements matching a certain CSS selector.
   *
   * @param \Behat\Mink\Element\NodeElement $parent
   *   The parent element.
   * @param string $css_selector
   *   The CSS selector to find child elements.
   *
   * @return string[]
   *   The text of all the child elements that matched the CSS selector, in the
   *   order that they appear.
   */
  private function getTextOfAll(NodeElement $parent, string $css_selector): array {
    return array_map(
      fn (NodeElement $element): string => $element->getText(),
      $parent->findAll('css', $css_selector),
    );
  }

}
