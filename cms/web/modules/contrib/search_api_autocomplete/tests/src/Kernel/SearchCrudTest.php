<?php

namespace Drupal\Tests\search_api_autocomplete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_test\MethodOverrides;
use Drupal\search_api_test\PluginTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests saving a Search API autocomplete config entity.
 *
 * @group search_api_autocomplete
 */
class SearchCrudTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_autocomplete',
    'search_api_autocomplete_test',
    'search_api',
    'search_api_db',
    'search_api_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig('search_api');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $server = Server::create([
      'id' => 'server',
      'name' => 'Server &!_1',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ]);
    $server->save();

    $index = Index::create([
      'id' => 'autocomplete_search_index',
      'name' => 'Index !1%$_',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:user' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $index->setServer($server);
    $index->save();
  }

  /**
   * Tests whether saving a new search entity works correctly.
   */
  public function testCreate() {
    MethodOverrides::$returnValue = [
      'config' => ['search_api.server.server'],
      'module' => ['user'],
    ];
    $this->setMethodOverride('search', 'calculateDependencies', [MethodOverrides::class, 'genericMethod']);

    $values = $this->getSearchTestValues();
    /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
    $search = Search::create($values);
    $search->save();

    $this->assertInstanceOf(SearchInterface::class, $search);

    $this->assertEquals($values['id'], $search->id());
    $this->assertEquals($values['label'], $search->label());
    $this->assertEquals($values['index_id'], $search->getIndexId());
    $actual = $search->getSuggesterIds();
    $this->assertEquals(array_keys($values['suggester_settings']), $actual);
    $actual = $search->getSuggester('server')->getConfiguration();
    $this->assertEquals($values['suggester_settings']['server'], $actual);
    $actual = $search->getSuggesterWeights();
    $this->assertEquals($values['suggester_weights'], $actual);
    $actual = $search->getSuggesterLimits();
    $this->assertEquals($values['suggester_limits'], $actual);
    $this->assertEquals('search_api_autocomplete_test', $search->getSearchPluginId());
    $actual = $search->getSearchPlugin()->getConfiguration();
    $this->assertEquals($values['search_settings']['search_api_autocomplete_test'], $actual);
    $this->assertEquals($values['options'], $search->getOptions());

    $expected = [
      'config' => [
        'search_api.index.autocomplete_search_index',
        'search_api.server.server',
      ],
      'module' => [
        'search_api_autocomplete_test',
        'user',
      ],
    ];
    $dependencies = $search->getDependencies();
    ksort($dependencies);
    sort($dependencies['config']);
    sort($dependencies['module']);
    $this->assertEquals($expected, $dependencies);
  }

  /**
   * Tests whether loading a search entity works correctly.
   */
  public function testLoad() {
    $values = $this->getSearchTestValues();
    $search = Search::create($values);
    $search->save();

    $loaded_search = Search::load($search->id());
    $this->assertInstanceOf(SearchInterface::class, $loaded_search);
    $this->assertEquals($search->toArray(), $loaded_search->toArray());
  }

  /**
   * Tests whether updating a search entity works correctly.
   */
  public function testUpdate() {
    $values = $this->getSearchTestValues();
    $search = Search::create($values);
    $search->save();

    $search->set('label', 'foobar');
    $search->save();

    $this->assertEquals('foobar', $search->label());
    $loaded_search = Search::load($search->id());
    $this->assertInstanceOf(SearchInterface::class, $loaded_search);
    $this->assertEquals($search->toArray(), $loaded_search->toArray());
  }

  /**
   * Tests whether deleting a search entity works correctly.
   *
   * @covers search_api_autocomplete_search_api_autocomplete_search_delete
   */
  public function testDelete() {
    $search = Search::create($this->getSearchTestValues());
    $search->save();

    // Make sure the search-specific permission exists, and will be removed from
    // all roles when the search is deleted.
    $all_permissions = \Drupal::service('user.permissions')->getPermissions();
    $permission = 'use search_api_autocomplete for muh';
    $this->assertArrayHasKey($permission, $all_permissions);
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test role',
      'permissions' => [$permission],
    ]);
    $role->save();
    $role = Role::load('test_role');
    $this->assertContains($permission, $role->getPermissions());
    $this->assertContains('search_api_autocomplete.search.muh', $role->getDependencies()['config']);

    $loaded_search = Search::load($search->id());
    $this->assertInstanceOf(SearchInterface::class, $loaded_search);

    $search->delete();

    $loaded_search = Search::load($search->id());
    $this->assertNull($loaded_search);

    // Try re-saving the role and make sure this doesn't lead to an exception,
    // as the permission has already been removed.
    $role = Role::load('test_role');
    $role->set('label', 'New label');
    $role->save();
    $this->assertNotContains($permission, $role->getPermissions());
  }

  /**
   * Retrieves properties for creating a test search entity.
   *
   * @return array
   *   Properties for an Autocomplete Search entity.
   */
  protected function getSearchTestValues() {
    return [
      'id' => 'muh',
      'label' => 'Meh',
      'index_id' => 'autocomplete_search_index',
      'suggester_settings' => [
        'server' => [
          'fields' => ['foo', 'bar'],
        ],
      ],
      'suggester_weights' => ['server' => -10],
      'suggester_limits' => ['server' => 5],
      'search_settings' => [
        'search_api_autocomplete_test' => [
          'foo' => 'bar',
        ],
      ],
      'options' => [
        'limit' => 8,
        'min_length' => 2,
        'show_count' => TRUE,
        'delay' => 1338,
        'submit_button_selector' => '#edit-submit',
        'autosubmit' => TRUE,
      ],
    ];
  }

}
