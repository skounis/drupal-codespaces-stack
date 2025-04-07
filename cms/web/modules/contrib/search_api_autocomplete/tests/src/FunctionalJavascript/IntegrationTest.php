<?php

namespace Drupal\Tests\search_api_autocomplete\FunctionalJavascript;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Tests\TestsHelper;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

/**
 * Tests the functionality of the whole module from a user's perspective.
 *
 * @group search_api_autocomplete
 */
class IntegrationTest extends IntegrationTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_autocomplete_test',
    'language',
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
  protected $searchId = 'search_api_autocomplete_test_view';

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
      'administer permissions',
      'view test entity',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);

    $this->normalUser = $this->drupalCreateUser();

    // For testing purposes, add a second language.
    ConfigurableLanguage::create([
      'id' => 'fr',
      'label' => 'French',
    ])->save();

    $this->setUpExampleStructure();
    $this->insertExampleContent();
  }

  /**
   * Tests the complete functionality of the module via the UI.
   */
  public function testModule() {
    $this->drupalLogin($this->adminUser);

    $this->enableSearch();
    $this->configureSearch();
    $this->checkEntityDependencies();
    $this->checkSearchAutocomplete();
    $this->checkSearchAutocomplete(TRUE);
    $this->checkLiveResultsAutocomplete();
    $this->checkCustomAutocompleteScript();
    $this->checkHooks();
    $this->checkPluginCacheClear();
    $this->checkAutocompleteAccess();
    $this->checkAdminAccess();
  }

  /**
   * Goes to the index's "Autocomplete" tab and creates/enables the test search.
   */
  protected function enableSearch() {
    $assert_session = $this->assertSession();

    $this->drupalGet($this->getAdminPath());

    // Check whether all expected groups and searches are present.
    $assert_session->pageTextContains('Search views');
    $assert_session->pageTextContains('Searches provided by Views');
    $assert_session->pageTextContains('Search API Autocomplete Test view');
    $assert_session->pageTextContains('Test search');
    $assert_session->pageTextContains('Autocomplete test module search');

    // Enable all Views searches (just one).
    $assert_session->checkboxNotChecked("searches[{$this->searchId}]");
    $this->click('table[data-drupal-selector="edit-search-views-searches"] > thead > tr > th.select-all input.form-checkbox');
    $assert_session->checkboxChecked("searches[{$this->searchId}]");

    $this->click('[data-drupal-selector="edit-actions-submit"]');
    $this->logPageChange(NULL, 'POST');
    $assert_session->pageTextContains('The settings have been saved. Remember to set the permissions for the newly enabled searches.');
  }

  /**
   * Configures the test search via the UI.
   */
  protected function configureSearch() {
    $assert_session = $this->assertSession();

    $this->click('.dropbutton-action a[href$="/edit"]');
    $this->logPageChange();
    $assert_session->addressEquals($this->getAdminPath('edit'));

    // The "Server" suggester shouldn't be available at that point.
    $assert_session->elementExists('css', 'input[name="suggesters[enabled][search_api_autocomplete_test]"]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][server]"]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][custom_script]"]');

    // Make the test backend support autocomplete so that the "Server" suggester
    // becomes available.
    $callback = [TestsHelper::class, 'getSupportedFeatures'];
    $this->setMethodOverride('backend', 'getSupportedFeatures', $callback);
    $callback = [TestsHelper::class, 'getAutocompleteSuggestions'];
    $this->setMethodOverride('backend', 'getAutocompleteSuggestions', $callback);

    // After refreshing, the "Server" suggester should now be available. But by
    // default, it should not be checked (one of the others should be the only
    // one). The "Custom scripts" suggester should not be available.
    $this->getSession()->reload();
    $this->logPageChange();
    $assert_session->checkboxNotChecked('suggesters[enabled][server]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][custom_script]"]');

    // The "Server" suggester's config form is hidden by default, but displayed
    // once we check its "Enabled" checkbox.
    $this->assertNotVisible('css', 'details[data-drupal-selector="edit-suggesters-settings-server"]');
    $this->click('input[name="suggesters[enabled][server]"]');
    $this->assertVisible('css', 'details[data-drupal-selector="edit-suggesters-settings-server"]');

    $page = $this->getSession()->getPage();
    $page->findButton('Show row weights')->click();
    $page->find('css', 'details[data-drupal-selector="edit-suggesters-settings-server"] > summary')->click();

    // Submit the form with some values for all fields.
    $edit = [
      'suggesters[enabled][live_results]' => FALSE,
      'suggesters[enabled][search_api_autocomplete_test]' => TRUE,
      'suggesters[weights][search_api_autocomplete_test][limit]' => '3',
      'suggesters[weights][server][limit]' => '3',
      'suggesters[weights][search_api_autocomplete_test][weight]' => '0',
      'suggesters[weights][server][weight]' => '10',
      'suggesters[settings][server][fields][name]' => FALSE,
      'suggesters[settings][server][fields][body]' => TRUE,
      'search_settings[displays][selected][default]' => FALSE,
      'options[limit]' => '5',
      'options[min_length]' => '2',
      'options[show_count]' => TRUE,
      'options[delay]' => '1000',
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
        "views.view.{$this->searchId}",
      ],
      'module' => [
        'search_api_autocomplete_test',
        'views',
      ],
    ];
    $dependencies = $search->getDependencies();
    ksort($dependencies);
    sort($dependencies['config']);
    sort($dependencies['module']);
    $this->assertEquals($expected, $dependencies);
  }

  /**
   * Tests autocompletion in the search form.
   *
   * @param bool $click_url_suggestion
   *   (optional) TRUE to click the URL-based suggestion, FALSE to click one of
   *   the "normal" search keys suggestions.
   */
  protected function checkSearchAutocomplete($click_url_suggestion = FALSE) {
    $assert_session = $this->assertSession();

    $this->drupalGet('search-api-autocomplete-test');

    $assert_session->elementAttributeContains('css', 'input[data-drupal-selector="edit-keys"]', 'data-search-api-autocomplete-search', $this->searchId);

    $elements = $this->getAutocompleteSuggestions();
    $suggestions = [];
    $suggestion_elements = [];
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
      $suggestion_elements[$keys] = $element;
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

    // Make sure the query looks as it should.
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    list($query) = $this->getMethodArguments('backend', 'getAutocompleteSuggestions');
    $this->assertFalse($query->wasAborted());
    $this->assertEquals(['body'], $query->getFulltextFields());
    $this->assertEquals(['en'], array_values($query->getLanguages()));

    if ($click_url_suggestion) {
      // Click the URL suggestion and verify it correctly redirects the browser
      // to that URL.
      $suggestion_elements['Tést-suggester-url']->click();
      $this->logPageChange();
      $assert_session->addressEquals("/user/{$this->adminUser->id()}");
      return;
    }

    // Click one of the search key suggestions. The form should now auto-submit.
    $keys = 'Tést-suggester-1';
    $suggestion_elements[$keys]->click();
    $this->logPageChange();
    $keys = urlencode($keys);
    // We cannot use $assert_session->addressMatches() as it is using clean URLs
    // and we therefore cannot test the part of the URL after the path.
    $this->assertMatchesRegularExpression("#/search-api-autocomplete-test\\?(?:.*&)?keys=$keys#", $this->getUrl());

    // Check that autocomplete in the "Name" filter works, too, and that it sets
    // the correct fields on the query.
    $this->getAutocompleteSuggestions('edit-name-value');
    list($query) = $this->getMethodArguments('suggester', 'getAutocompleteSuggestions');
    $this->assertEquals(['name'], $query->getFulltextFields());
  }

  /**
   * Tests autocomplete with the "Live results" suggester.
   */
  protected function checkLiveResultsAutocomplete() {
    $assert_session = $this->assertSession();

    // First, enable "Live results" as the only suggester.
    $this->drupalGet($this->getAdminPath('edit'));
    $page = $this->getSession()->getPage();
    $this->click('input[name="suggesters[enabled][live_results]"]');
    $page->find('css', 'details[data-drupal-selector="edit-suggesters-settings-live-results"] > summary')->click();
    $edit = [
      'suggesters[enabled][live_results]' => TRUE,
      'suggesters[enabled][search_api_autocomplete_test]' => FALSE,
      'suggesters[enabled][server]' => FALSE,
      'suggesters[settings][live_results][fields][name]' => FALSE,
      'suggesters[settings][live_results][fields][body]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('The autocompletion settings for the search have been saved.');

    // Then, set an appropriate search method for the test backend.
    $callback = [TestsHelper::class, 'search'];
    $this->setMethodOverride('backend', 'search', $callback);

    // Get the autocompletion results.
    $this->drupalGet('search-api-autocomplete-test');
    $suggestions = [];
    foreach ($this->getAutocompleteSuggestions() as $element) {
      $label = $this->getElementText($element, '.autocomplete-suggestion-label');
      $suggestions[$label] = $element;
    }

    // Make sure the suggestions are as expected.
    $expected = [
      $this->entities[3]->label(),
      $this->entities[4]->label(),
      $this->entities[2]->label(),
    ];
    $this->assertEquals($expected, array_keys($suggestions));

    // Make sure all the search query settings were as expected.
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $this->getMethodArguments('backend', 'search')[0];
    $this->assertInstanceOf(QueryInterface::class, $query);
    $this->assertEquals(0, $query->getOption('offset'));
    $this->assertEquals(5, $query->getOption('limit'));
    $this->assertEquals(['body'], $query->getFulltextFields());
    $this->assertEquals('Tést', $query->getOriginalKeys());

    // Click on one of the suggestions and verify it takes us to the expected
    // page.
    $suggestions[$this->entities[3]->label()]->click();
    $this->logPageChange();
    $path = $this->entities[3]->toUrl()->getInternalPath();
    $assert_session->addressEquals('/' . $path);
  }

  /**
   * Tests whether using a custom autocomplete script is properly supported.
   *
   * @see \Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\CustomScript
   */
  protected function checkCustomAutocompleteScript() {
    $assert_session = $this->assertSession();

    \Drupal::configFactory()
      ->getEditable('search_api_autocomplete.settings')
      ->set('enable_custom_scripts', TRUE)
      ->save();

    $this->drupalGet($this->getAdminPath('edit'));
    $path = \Drupal::moduleHandler()
      ->getModule('search_api_autocomplete')
      ->getPath();
    $path = "/$path/tests/search_api_autocomplete_test/core/custom_autocomplete_script.php";
    $this->click('input[name="suggesters[enabled][custom_script]"]');
    $page = $this->getSession()->getPage();
    $page->find('css', 'details[data-drupal-selector="edit-suggesters-settings-custom-script"] > summary')
      ->click();
    $edit = [
      'suggesters[enabled][custom_script]' => TRUE,
      'suggesters[settings][custom_script][path]' => $path,
    ];
    $this->submitForm($edit, 'Save');

    $this->drupalGet('search-api-autocomplete-test');

    $assert_session->elementAttributeContains('css', 'input[data-drupal-selector="edit-keys"]', 'data-search-api-autocomplete-search', $this->searchId);

    $elements = $this->getAutocompleteSuggestions();
    $this->assertCount(4, $elements);
    $suggestions = [];
    foreach ($elements as $element) {
      $suggestions[] = $element->getText();
    }
    sort($suggestions);
    $expected = [
      'display: page',
      'filter: keys',
      'q: Tést',
      "search_api_autocomplete_search: {$this->searchId}",
    ];
    $this->assertEquals($expected, $suggestions, 'Unexpected suggestions returned by custom script.');

    $this->drupalGet($this->getAdminPath('edit'));
    $page = $this->getSession()->getPage();
    $page->find('css', 'details[data-drupal-selector="edit-suggesters-settings-custom-script"] > summary')->click();
    $edit = [
      'suggesters[settings][custom_script][path]' => '',
      'suggesters[enabled][custom_script]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Checks that the module's hooks work as expected.
   */
  protected function checkHooks() {
    $assert_session = $this->assertSession();

    \Drupal::getContainer()->get('module_installer')->install([
      'search_api_autocomplete_test_hooks',
    ]);

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextContains('The Siren');
    $assert_session->pageTextContains('Planet Hell');
    $assert_session->pageTextNotContains('Search views');
    $assert_session->pageTextNotContains('Searches provided by Views');

    $this->drupalGet($this->getAdminPath('edit'));
    $assert_session->pageTextContains('Wish I Had an Angel');
    $assert_session->pageTextNotContains('Test suggester');

    $this->drupalGet('search-api-autocomplete-test');
    $assert_session->pageTextContains("Creek Mary's Blood");

    $autocomplete_path = "search_api_autocomplete/{$this->searchId}";
    $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
    $assert_session->responseContains('dark chest of wonders');

    \Drupal::getContainer()->get('module_installer')->uninstall([
      'search_api_autocomplete_test_hooks',
    ]);
  }

  /**
   * Verifies that creating or deleting a view clears the search plugin cache.
   */
  protected function checkPluginCacheClear() {
    $assert_session = $this->assertSession();
    $new_view_label = 'Search plugin cache test';

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextNotContains($new_view_label);

    $view = View::load('search_api_autocomplete_test_view')->createDuplicate();
    $view->set('id', 'search_plugin_cache_test');
    $view->set('label', $new_view_label);
    $display = $view->get('display');
    $display['page']['display_options']['path'] = 'some/new/path';
    $view->set('display', $display);
    $view->save();

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextContains($new_view_label);

    $view->delete();

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextNotContains($new_view_label);
  }

  /**
   * Verifies that autocomplete is only applied after access checks.
   */
  protected function checkAutocompleteAccess() {
    $assert_session = $this->assertSession();

    // Make sure autocomplete functionality is only available for users with the
    // right permission.
    $users = [
      'non-admin' => $this->normalUser,
      'anonymous' => NULL,
    ];
    $permission = "use search_api_autocomplete for {$this->searchId}";
    $autocomplete_path = "search_api_autocomplete/{$this->searchId}";
    foreach ($users as $user_type => $account) {
      $this->drupalLogout();
      if ($account) {
        $this->drupalLogin($account);
      }

      $this->drupalGet('search-api-autocomplete-test');
      $element = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-keys"]');
      $this->assertFalse($element->hasAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertFalse($element->hasClass('form-autocomplete'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");

      $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
      $assert_session->pageTextContains('Access denied');
      $assert_session->pageTextContains('You are not authorized to access this page.');

      $rid = $account ? 'authenticated' : 'anonymous';
      $role = Role::load($rid);
      $role->grantPermission($permission);
      $role->save();

      $this->drupalGet('search-api-autocomplete-test');
      $element = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-keys"]');
      $this->assertTrue($element->hasAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertStringContainsString($this->searchId, $element->getAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertTrue($element->hasClass('form-autocomplete'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");

      $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
    }
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Verifies that admin pages are properly protected.
   */
  protected function checkAdminAccess() {
    $assert_session = $this->assertSession();

    // Make sure anonymous and non-admin users cannot access admin pages.
    $users = [
      'non-admin' => $this->normalUser,
      'anonymous' => NULL,
    ];
    $paths = [
      'index overview' => $this->getAdminPath(),
      'search edit form' => $this->getAdminPath('edit'),
      'search delete form' => $this->getAdminPath('delete'),
    ];
    foreach ($users as $user_type => $account) {
      $this->drupalLogout();
      if ($account) {
        $this->drupalLogin($account);
      }
      foreach ($paths as $label => $path) {
        $this->drupalGet($path);
        $assert_session->pageTextContains('Access denied');
        $assert_session->pageTextContains('You are not authorized to access this page.');
      }
    }
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Returns the path of an admin page.
   *
   * @param string|null $page
   *   (optional) Either "edit" or "delete" to get the path of the respective
   *   search form, or NULL for the index's "Autocomplete" tab.
   * @param string|null $search_id
   *   (optional) The ID of the search to link to, if a page is specified. NULL
   *   to use the default search used by this test.
   *
   * @return string
   *   The internal path to the specified page.
   */
  protected function getAdminPath($page = NULL, $search_id = NULL) {
    $path = 'admin/config/search/search-api/index/autocomplete_search_index/autocomplete';
    if ($page !== NULL) {
      if ($search_id === NULL) {
        $search_id = $this->searchId;
      }
      $path .= "/$search_id/$page";
    }
    return $path;
  }

  /**
   * Logs a page change, if HTML output logging is enabled.
   *
   * The base class only logs requests when the drupalGet() or drupalPost()
   * methods are used, so we need to implement this ourselves for other page
   * changes.
   *
   * To enable HTML output logging, create some file where links to the logged
   * pages should be placed and set the "BROWSERTEST_OUTPUT_FILE" environment
   * variable to that file's path.
   *
   * @param string|null $url
   *   (optional) The URL requested, if not the current URL.
   * @param string $method
   *   (optional) The HTTP method used for the request.
   *
   * @see \Drupal\Tests\BrowserTestBase::drupalGet()
   * @see \Drupal\Tests\BrowserTestBase::setUp()
   */
  protected function logPageChange($url = NULL, $method = 'GET') {
    $session = $this->getSession();
    $driver = $session->getDriver();
    if (!$this->htmlOutputEnabled || $driver instanceof BrowserKitDriver) {
      return;
    }
    $current_url = $session->getCurrentUrl();
    $url = $url ?: $current_url;
    $html_output = "$method request to: $url<hr />Ending URL: $current_url";
    $html_output .= '<hr />' . $session->getPage()->getContent();;
    $html_output .= $this->getHtmlOutputHeaders();
    $this->htmlOutput($html_output);
  }

}
