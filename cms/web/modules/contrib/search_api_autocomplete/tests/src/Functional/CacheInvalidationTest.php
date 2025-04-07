<?php

namespace Drupal\Tests\search_api_autocomplete\Functional;

use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;

/**
 * Tests whether caches are always invalidated correctly.
 *
 * @group search_api_autocomplete
 */
class CacheInvalidationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'search_api_autocomplete_test',
    'search_api_autocomplete_test_pages',
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

    // Change the view to use an exposed form block.
    $view_id = 'search_api_autocomplete_test_view';
    $view = View::load($view_id);
    $displays = $view->get('display');
    $displays['page']['display_options']['exposed_block'] = TRUE;
    $view->set('display', $displays);
    $view->save();

    // Enable the exposed form block.
    $this->placeBlock("views_exposed_filter_block:$view_id-page");

    // Enable the search page block.
    $this->placeBlock('search_api_page_form_block', [
      'search_api_page' => 'test_search',
    ]);

    // Enable the "Custom scripts" suggester.
    \Drupal::configFactory()
      ->getEditable('search_api_autocomplete.settings')
      ->set('enable_custom_scripts', TRUE)
      ->save();

    // Log in an admin user so we don't run into any access-related
    // difficulties.
    $this->drupalLogin($this->createUser([
      'administer search_api',
      'administer search_api_autocomplete',
      'administer search_api_page',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function testCacheInvalidation() {
    $assert_session = $this->assertSession();

    // First, verify that no autocomplete is present by default, thus also
    // priming the render cache.
    $this->drupalGet('');

    $input_selector = 'input[data-drupal-selector="edit-keys"]';
    $views_selector = ".views-exposed-form $input_selector";
    $element = $assert_session->elementExists('css', $views_selector);
    $attribute = 'data-search-api-autocomplete-search';
    $this->assertFalse($element->hasAttribute($attribute));

    $page_selector = ".search-api-page-block-form $input_selector";
    $element = $assert_session->elementExists('css', $page_selector);
    $this->assertFalse($element->hasAttribute($attribute));

    // Then, add autocomplete settings for both searches.
    $views_search = Search::create([
      'id' => 'search_api_autocomplete_test_view',
      'label' => 'Search API Autocomplete Test view',
      'status' => TRUE,
      'index_id' => 'autocomplete_search_index',
      'suggester_settings' => [
        'custom_script' => [
          'path' => '/foo',
        ],
      ],
      'search_settings' => [
        'views:search_api_autocomplete_test_view' => [],
      ],
    ]);
    $views_search->save();

    $page_search = Search::create([
      'id' => 'test_search',
      'label' => 'Test search page',
      'status' => TRUE,
      'index_id' => 'autocomplete_search_index',
      'suggester_settings' => [
        'custom_script' => [
          'path' => '/bar',
        ],
      ],
      'search_settings' => [
        'page:test_search' => [],
      ],
    ]);
    $page_search->save();

    // View the page again and verify that autocomplete was now added for both
    // forms.
    $this->drupalGet('');

    $assert_session->elementAttributeContains('css', $views_selector, 'data-search-api-autocomplete-search', $views_search->id());
    $assert_session->elementAttributeContains('css', $views_selector, 'data-autocomplete-path', '/foo');

    $assert_session->elementAttributeContains('css', $page_selector, 'data-search-api-autocomplete-search', $page_search->id());
    $assert_session->elementAttributeContains('css', $page_selector, 'data-autocomplete-path', '/bar');

    // Change the autocomplete search settings.
    $views_search->getSuggester('custom_script')->setConfiguration([
      'path' => '/foobar',
    ]);
    $views_search->save();

    $page_search->getSuggester('custom_script')->setConfiguration([
      'path' => '/foo/bar',
    ]);
    $page_search->save();

    // Verify the changes are correctly applied when reloading the page.
    $this->drupalGet('');

    $assert_session->elementAttributeContains('css', $views_selector, 'data-search-api-autocomplete-search', $views_search->id());
    $assert_session->elementAttributeContains('css', $views_selector, 'data-autocomplete-path', '/foobar');

    $assert_session->elementAttributeContains('css', $page_selector, 'data-search-api-autocomplete-search', $page_search->id());
    $assert_session->elementAttributeContains('css', $page_selector, 'data-autocomplete-path', '/foo/bar');
  }

}
