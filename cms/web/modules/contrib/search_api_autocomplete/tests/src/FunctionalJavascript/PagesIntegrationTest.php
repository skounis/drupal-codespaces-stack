<?php

namespace Drupal\Tests\search_api_autocomplete\FunctionalJavascript;

use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Tests\TestsHelper;
use Drupal\search_api_page\Entity\SearchApiPage;

/**
 * Tests integration with the Search API Pages module.
 *
 * @requires module search_api_page
 * @group search_api_autocomplete
 */
class PagesIntegrationTest extends IntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_autocomplete_test_pages',
  ];

  /**
   * The ID of the search index used in this test.
   *
   * @var string
   */
  protected $indexId = 'autocomplete_search_index';

  /**
   * The ID of the search entity created for this test.
   *
   * @var string
   */
  protected $searchId = 'test_search';

  /**
   * An admin user used for the tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A normal (non-admin) user used for the tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'administer search_api',
      'administer search_api_autocomplete',
      'view search api pages',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);

    $this->normalUser = $this->drupalCreateUser();

    // Make the test backend support autocomplete so that the "Server" suggester
    // becomes available.
    $callback = [TestsHelper::class, 'getSupportedFeatures'];
    $this->setMethodOverride('backend', 'getSupportedFeatures', $callback);
    $callback = [TestsHelper::class, 'getAutocompleteSuggestions'];
    $this->setMethodOverride('backend', 'getAutocompleteSuggestions', $callback);
  }

  /**
   * Tests autocomplete for search pages.
   */
  public function testModule() {
    $this->drupalLogin($this->adminUser);

    $this->enableSearch();
    $this->checkEntityDependencies();
    $this->checkAutocompleteFunctionality();
    $this->checkSearchPluginCacheClear();
  }

  /**
   * Enables the search.
   */
  protected function enableSearch() {
    $assert_session = $this->assertSession();

    $this->drupalGet($this->getAdminPath());

    // Check whether all expected groups and searches are present.
    $assert_session->pageTextContains('Search pages');
    $assert_session->pageTextContains('Searches provided by the Search pages module');
    $assert_session->pageTextContains('Test search page');

    // Enable autocomplete for all search pages (just one).
    $assert_session->checkboxNotChecked("searches[{$this->searchId}]");
    $this->click('table[data-drupal-selector="edit-search-pages-searches"] > thead > tr > th.select-all input.form-checkbox');
    $assert_session->checkboxChecked("searches[{$this->searchId}]");

    // Save the settings.
    $this->click('[data-drupal-selector="edit-actions-submit"]');
    $this->logPageChange(NULL, 'POST');
    $assert_session->pageTextContains('The settings have been saved.');
    // Our admin user for this test doesn't have the "administer permissions"
    // permission, so the permission reminder should not be included.
    $assert_session->pageTextNotContains('Remember to set the permissions for the newly enabled searches.');

    // Edit the search.
    $this->click('.dropbutton-action a[href$="/edit"]');
    $this->logPageChange();
    $assert_session->addressEquals($this->getAdminPath('edit'));
    $page = $this->getSession()->getPage();
    $page->findButton('Show row weights')->click();
    $edit = [
      'suggesters[enabled][server]' => TRUE,
      'suggesters[enabled][search_api_autocomplete_test]' => TRUE,
      'suggesters[weights][search_api_autocomplete_test][limit]' => '3',
      'suggesters[weights][server][limit]' => '3',
      'suggesters[weights][search_api_autocomplete_test][weight]' => '0',
      'suggesters[weights][server][weight]' => '10',
      'options[limit]' => '5',
      'options[min_length]' => '2',
      'options[show_count]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Verifies that the search entity's dependencies were calculated correctly.
   */
  protected function checkEntityDependencies() {
    /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
    $search = Search::load($this->searchId);
    $expected = [
      'config' => [
        'search_api.index.autocomplete_search_index',
        "search_api_page.search_api_page.{$this->searchId}",
      ],
      'module' => [
        'search_api_autocomplete_test',
        'search_api_page',
      ],
    ];
    $dependencies = $search->getDependencies();
    ksort($dependencies);
    sort($dependencies['config']);
    sort($dependencies['module']);
    $this->assertEquals($expected, $dependencies);
  }

  /**
   * Checks that autocomplete works correctly.
   */
  protected function checkAutocompleteFunctionality() {
    $assert_session = $this->assertSession();

    $this->drupalGet('test-search');

    $assert_session->elementAttributeContains('css', 'input[data-drupal-selector="edit-keys"]', 'data-search-api-autocomplete-search', $this->searchId);

    $elements = $this->getAutocompleteSuggestions();
    $suggestions = [];
    foreach ($elements as $element) {
      $label = $this->getElementText($element, '.autocomplete-suggestion-label');
      $user_input = $this->getElementText($element, '.autocomplete-suggestion-user-input');
      $suffix = $this->getElementText($element, '.autocomplete-suggestion-suggestion-suffix');
      $count = $this->getElementText($element, '.autocomplete-suggestion-results-count');
      $keys = $label . $user_input . $suffix;
      $suggestions[] = [
        'keys' => $keys,
        'count' => $count,
      ];
    }
    $expected = [
      [
        'keys' => 'Tést-suggester-1',
        'count' => 1,
      ],
      [
        'keys' => 'Tést-suggester-2',
        'count' => 2,
      ],
      [
        'keys' => 'Tést-suggester-url',
        'count' => NULL,
      ],
      [
        'keys' => 'Tést-backend-1',
        'count' => 1,
      ],
      [
        'keys' => 'Tést-backend-2',
        'count' => 2,
      ],
    ];
    $this->assertEquals($expected, $suggestions);

    /** @var \Drupal\search_api\Query\QueryInterface $query */
    list($query) = $this->getMethodArguments('backend', 'getAutocompleteSuggestions');
    $this->assertEquals(['name'], $query->getFulltextFields());

    $this->drupalGet($this->getAdminPath('edit'));
    $page = $this->getSession()->getPage();
    $page->find('css', '#edit-suggesters-settings-server > summary')->click();
    $edit = [
      'suggesters[settings][server][fields][body]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    $this->drupalGet('test-search');

    $elements = $this->getAutocompleteSuggestions();
    $this->assertCount(5, $elements);

    list($query) = $this->getMethodArguments('backend', 'getAutocompleteSuggestions');
    $this->assertEquals(['body'], $query->getFulltextFields());
  }

  /**
   * Tests whether the search plugin cache is cleared correctly.
   */
  protected function checkSearchPluginCacheClear() {
    $assert_session = $this->assertSession();

    $search = Search::load($this->searchId);
    $this->assertInstanceOf(SearchInterface::class, $search);

    $page = SearchApiPage::load($this->searchId);
    $page2 = $page->createDuplicate();
    $page2->set('id', 'foobar');
    $page2->set('label', 'Foobar');

    $this->drupalGet($this->getAdminPath());

    $assert_session->pageTextContains('Test search page');
    $assert_session->pageTextNotContains('Foobar');

    $page2->save();
    $this->drupalGet($this->getAdminPath());

    $assert_session->pageTextContains('Test search page');
    $assert_session->pageTextContains('Foobar');

    $page->delete();
    $this->drupalGet($this->getAdminPath());

    $assert_session->pageTextNotContains('Test search page');
    $assert_session->pageTextContains('Foobar');

    $search = Search::load($this->searchId);
    $this->assertNull($search);
  }

}
