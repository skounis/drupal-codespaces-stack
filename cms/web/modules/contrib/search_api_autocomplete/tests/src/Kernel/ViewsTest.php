<?php

namespace Drupal\Tests\search_api_autocomplete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api_autocomplete\Element\SearchApiAutocomplete as AutocompleteElement;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Search\SearchPluginManager;
use Drupal\search_api_autocomplete\Utility\PluginHelper;
use Drupal\views\Entity\View;

/**
 * Tests Views integration of the Autocomplete module.
 *
 * @group search_api_autocomplete
 */
class ViewsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'search_api_autocomplete',
    'search_api_autocomplete_test',
    'search_api',
    'search_api_test',
    'search_api_test_example_content',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig('search_api');
    $this->installConfig('search_api_test_example_content');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }
  }

  /**
   * Tests that valid search plugin definitions are created for search views.
   */
  public function testSearchPlugin() {
    $index_id = 'autocomplete_search_index';
    $plugin_helper = $this->container
      ->get('search_api_autocomplete.plugin_helper');

    $plugins = $plugin_helper->createSearchPluginsForIndex($index_id);
    $view_id = 'views:search_api_autocomplete_test_view';
    $this->assertArrayNotHasKey($view_id, $plugins);

    $this->installConfig('search_api_autocomplete_test');

    // To avoid getting the cached derivatives from the Views search plugin
    // deriver, we unfortunately need to rebuild the search plugin manager. This
    // is probably the simplest way to do it, without too many side effects.
    $search_plugin_manager = new SearchPluginManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler')
    );
    $plugin_helper = new PluginHelper(
      $this->container->get('plugin.manager.search_api_autocomplete.suggester'),
      $search_plugin_manager
    );

    $plugins = $plugin_helper->createSearchPluginsForIndex($index_id);
    $this->assertArrayHasKey($view_id, $plugins);
    /** @var \Drupal\search_api_autocomplete\Search\SearchPluginInterface $plugin */
    $plugin = $plugins[$view_id];
    $this->assertEquals('Search API Autocomplete Test view', $plugin->label());
    $this->assertEquals('Search views', $plugin->getGroupLabel());
    $this->assertEquals('Searches provided by Views', $plugin->getGroupDescription());

    $data = [
      'display' => 'page',
      'filter' => 'keys',
    ];
    $query = $plugin->createQuery('foobar', $data);
    $this->assertEquals('foobar', $query->getOriginalKeys());
    $index = $query->getIndex();
    $this->assertEquals($index_id, $index->id());
    $fields = $query->getFulltextFields();
    $all_fulltext_fields = $index->getFulltextFields();
    $fields = isset($fields) ? $fields : $all_fulltext_fields;
    $this->assertEquals($all_fulltext_fields, $fields);

    $query = $plugin->createQuery('', $data);
    $this->assertNull($query->getOriginalKeys());

    $data = [
      'display' => 'page',
      'filter' => 'name',
      'field' => 'name',
    ];
    $query = $plugin->createQuery('foobar', $data);
    $this->assertNull($query->getOriginalKeys());
    $this->assertStringContainsString('foobar', (string) $query);
    $conditions = $query->getConditionGroup()->getConditions();
    $conditions = $this->collectConditions($conditions);
    $this->assertCount(1, $conditions);
    $condition = $conditions[0];
    $this->assertEquals('name', $condition->getField());
    $this->assertEquals('foobar', $condition->getValue());
    $this->assertEquals($index_id, $query->getIndex()->id());
    $this->assertEquals(['name'], $query->getFulltextFields());
  }

  /**
   * Collects conditions from an array of conditions and condition groups.
   *
   * Any information about condition nesting, group operators, etc. is lost.
   * Only makes sense for tests.
   *
   * @param \Drupal\search_api\Query\ConditionInterface[]|\Drupal\search_api\Query\ConditionGroupInterface[] $conditions
   *   An array of conditions and condition groups.
   *
   * @return \Drupal\search_api\Query\ConditionInterface[]
   *   All conditions contained in the given array of conditions and condition
   *   groups.
   */
  protected function collectConditions(array $conditions) {
    $ret = [];

    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        $ret[] = $condition;
      }
      else {
        $new = $this->collectConditions($condition->getConditions());
        $ret = array_merge($ret, $new);
      }
    }

    return $ret;
  }

  /**
   * Tests that Views forms are altered correctly.
   *
   * @see search_api_autocomplete_form_views_exposed_form_alter()
   *
   * @dataProvider formAlteringDataProvider
   */
  public function testFormAltering($display_id, $expect_altered) {
    $this->installConfig('search_api_autocomplete_test');

    Search::create([
      'id' => 'search_api_autocomplete_test_view',
      'label' => 'Search API Autocomplete Test view',
      'index_id' => 'autocomplete_search_index',
      'suggester_settings' => [
        'live_results' => [],
      ],
      'search_settings' => [
        'views:search_api_autocomplete_test_view' => [
          'displays' => [
            'default' => TRUE,
            'selected' => ['page_2'],
          ],
        ],
      ],
    ])->save();

    $view = View::load('search_api_autocomplete_test_view');
    /** @var \Drupal\views\ViewExecutable $executable */
    $executable = $view->getExecutable();
    $this->assertTrue($executable->setDisplay($display_id));
    $executable->initHandlers();

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_form */
    $exposed_form = $executable->display_handler->getPlugin('exposed_form');
    $form = $exposed_form->renderExposedForm();

    $keys_element = $form['keys'] ?? $form['keys_wrapper']['keys'];
    if ($expect_altered) {
      $this->assertEquals('search_api_autocomplete', $keys_element['#type']);
    }
    else {
      $this->assertEquals('textfield', $keys_element['#type']);
    }

    // Check that the "#process" callbacks were altered correctly in
    // AutocompleteHelper::alterElement().
    if ($display_id === 'exposed_form_plugin' &&
        $expect_altered &&
        !empty($keys_element['#process'])) {
      $element_class = AutocompleteElement::class;
      $element_class_methods = [];
      foreach ($keys_element['#process'] as $process_callback) {
        $process_callback_method = '';
        if (is_array($process_callback)) {
          $process_callback_method = implode('::', $process_callback);
        }
        elseif (is_string($process_callback)) {
          $process_callback_method = $process_callback;
        }

        if (str_starts_with($process_callback_method, "$element_class::") ||
            str_starts_with($process_callback_method, 'Drupal\Core\Render\Element\Textfield::')) {
          $process_callback_parts = explode('::', $process_callback_method);
          $element_class_methods[$process_callback_parts[1]][] = $process_callback_method;
        }
      }

      $duplicate_class_methods = array_filter($element_class_methods, function ($v) {
        return count($v) > 1;
      });
      $this->assertEmpty(
        $duplicate_class_methods,
        'There should not be duplicate element #process callbacks. The following methods are duplicated: ' . print_r($duplicate_class_methods, TRUE),
      );
    }
  }

  /**
   * Provides test data for testFormAltering().
   *
   * @return array
   *   Array of argument arrays for testFormAltering().
   *
   * @see \Drupal\Tests\search_api_autocomplete\Kernel\ViewsTest::testFormAltering()
   */
  public static function formAlteringDataProvider(): array {
    return [
      'do alter' => ['page', TRUE],
      "don't alter" => ['page_2', FALSE],
      'exposed_form_plugin' => ['exposed_form_plugin', TRUE],
    ];
  }

  /**
   * Tests that the deriver works correctly.
   *
   * @see \Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search\ViewsDeriver
   */
  public function testDeriver() {
    $searches = $this->container
      ->get('plugin.manager.search_api_autocomplete.search')
      ->getDefinitions();
    $expected = ['search_api_autocomplete_test'];
    $this->assertEquals($expected, array_keys($searches));

    $this->installConfig('search_api_autocomplete_test');

    $searches = $this->container
      ->get('plugin.manager.search_api_autocomplete.search')
      ->getDefinitions();
    ksort($searches);
    $expected = [
      'search_api_autocomplete_test',
      'views:search_api_autocomplete_test_view',
    ];
    $this->assertEquals($expected, array_keys($searches));

    View::create([
      'id' => 'second_test_view',
      'base_field' => 'search_api_id',
      'base_table' => 'search_api_index_autocomplete_search_index',
      'core' => '8.x',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [
            'query' => [
              'type' => 'search_api_query',
            ],
          ],
        ],
      ],
    ])->save();

    $searches = $this->container
      ->get('plugin.manager.search_api_autocomplete.search')
      ->getDefinitions();
    ksort($searches);
    $expected = [
      'search_api_autocomplete_test',
      'views:search_api_autocomplete_test_view',
      'views:second_test_view',
    ];
    $this->assertEquals($expected, array_keys($searches));
  }

}
