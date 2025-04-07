<?php

namespace Drupal\Tests\search_api_autocomplete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Tests\TestsHelper;
use Drupal\search_api_test\PluginTestTrait;
use Drupal\views\Entity\View;

/**
 * Tests dependency handling of the search entity.
 *
 * @group search_api_autocomplete
 */
class DependencyRemovalTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'search_api_autocomplete',
    'search_api_autocomplete_test',
    'search_api',
    'search_api_test',
    'search_api_test_example_content',
    'system',
    'user',
    'views',
  ];

  /**
   * The autocomplete search entity used in this test.
   *
   * @var \Drupal\search_api_autocomplete\SearchInterface
   */
  protected $search;

  /**
   * A config entity, to be used as a dependency in the tests.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $dependency;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig('search_api');
    $this->installConfig('search_api_autocomplete_test');

    // Create our test search, but don't save it yet so individual tests can
    // still easily change the settings.
    $this->search = Search::create([
      'id' => 'search_api_autocomplete_test_view',
      'label' => 'Test',
      'status' => TRUE,
      'index_id' => 'autocomplete_search_index',
      'suggester_settings' => [
        'search_api_autocomplete_test' => [],
      ],
      'search_settings' => [
        'views:search_api_autocomplete_test_view' => [],
      ],
    ]);

    // Use a search server as the dependency, since we have that available
    // anyways. The entity type should not matter at all, though.
    $this->dependency = Server::create([
      'id' => 'dependency',
      'name' => 'Test dependency',
      'backend' => 'search_api_test',
    ]);
    $this->dependency->save();
  }

  /**
   * Tests that the dependency on the index works correctly.
   */
  public function testIndexDependency() {
    $this->search->save();
    $index = $this->search->getIndex();

    // Verify that the dependency is included.
    $dependencies = $this->search->getDependencies();
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains($index->getConfigDependencyName(), $dependencies['config']);

    // Verify that deleting the index will also delete the search.
    $index->delete();
    $search = Search::load($this->search->id());
    $this->assertNull($search);
  }

  /**
   * Tests that the Views dependency works correctly for a search view.
   */
  public function testViewsDependency() {
    $this->search->save();
    $view = View::load('search_api_autocomplete_test_view');

    // Verify that the dependencies are both included.
    $dependencies = $this->search->getDependencies();
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains($view->getConfigDependencyName(), $dependencies['config']);
    $this->assertArrayHasKey('module', $dependencies);
    $this->assertContains('views', $dependencies['module']);

    // Verify that deleting the view will also delete the search.
    $view->delete();
    $search = Search::load($this->search->id());
    $this->assertNull($search);
  }

  /**
   * Tests that a general search plugin dependency works correctly.
   *
   * @param bool $removable
   *   TRUE if the search plugin's dependency should be removable, FALSE
   *   otherwise.
   *
   * @dataProvider searchPluginDependencyDataProvider
   */
  public function testSearchPluginDependency($removable) {
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();
    $this->search->set('search_settings', [
      'search_api_autocomplete_test' => [
        'dependencies' => [
          $dependency_key => [$dependency_name],
        ],
      ],
    ]);
    $this->search->save();

    $this->setReturnValue('search', 'onDependencyRemoval', $removable);

    // Verify that the dependencies are all included.
    $dependencies = $this->search->getDependencies();
    $this->assertArrayHasKey($dependency_key, $dependencies);
    $this->assertContains($dependency_name, $dependencies[$dependency_key]);
    $this->assertArrayHasKey('module', $dependencies);
    $this->assertContains('search_api_autocomplete_test', $dependencies['module']);

    // Delete the dependency and verify that the result is as expected.
    $this->dependency->delete();

    $search = Search::load($this->search->id());
    if ($removable) {
      $this->assertNotNull($search);
      $dependencies = $search->getDependencies();
      $dependencies += [$dependency_key => []];
      $this->assertNotContains($dependency_name, $dependencies[$dependency_key]);
    }
    else {
      $this->assertNull($search);
    }
  }

  /**
   * Provides test data sets for testSearchPluginDependency().
   *
   * @return array[]
   *   An array of argument arrays for testSearchPluginDependency().
   *
   * @see \Drupal\Tests\search_api_autocomplete\Kernel\DependencyRemovalTest::testSearchPluginDependency()
   */
  public static function searchPluginDependencyDataProvider(): array {
    return [
      'soft dependency' => [TRUE],
      'hard dependency' => [FALSE],
    ];
  }

  /**
   * Tests that a suggester dependency works correctly.
   *
   * @param bool $removable
   *   TRUE if the suggester's dependency should be removable, FALSE otherwise.
   * @param bool $second_suggester
   *   TRUE if a second suggester, apart from the test suggester, should be
   *   included in the search, FALSE otherwise.
   *
   * @dataProvider suggesterDependencyDataProvider
   */
  public function testSuggesterDependency($removable, $second_suggester) {
    $dependency_key = $this->dependency->getConfigDependencyKey();
    $dependency_name = $this->dependency->getConfigDependencyName();
    $suggester_id = 'search_api_autocomplete_test';
    $settings = [
      $suggester_id => [
        'dependencies' => [
          $dependency_key => [$dependency_name],
        ],
      ],
    ];
    if ($second_suggester) {
      // Make the test backend support autocomplete so that the "Server"
      // suggester becomes available.
      $callback = [TestsHelper::class, 'getSupportedFeatures'];
      $this->setMethodOverride('backend', 'getSupportedFeatures', $callback);
      $callback = [TestsHelper::class, 'getAutocompleteSuggestions'];
      $this->setMethodOverride('backend', 'getAutocompleteSuggestions', $callback);
      $settings['server'] = [];
    }
    $this->search->set('suggester_settings', $settings);
    $this->search->save();

    $this->setReturnValue('suggester', 'onDependencyRemoval', $removable);

    // Verify that the dependencies are all included.
    $dependencies = $this->search->getDependencies();
    $this->assertArrayHasKey($dependency_key, $dependencies);
    $this->assertContains($dependency_name, $dependencies[$dependency_key]);
    $this->assertArrayHasKey('module', $dependencies);
    $this->assertContains('search_api_autocomplete_test', $dependencies['module']);

    // Delete the dependency and verify that the result is as expected.
    $this->dependency->delete();

    $search = Search::load($this->search->id());
    $this->assertNotNull($search);
    $this->assertEquals($removable || $second_suggester, $search->status());
    $dependencies = $search->getDependencies();
    $dependencies += [$dependency_key => []];
    $this->assertNotContains($dependency_name, $dependencies[$dependency_key]);
    $this->assertEquals($removable, $search->isValidSuggester($suggester_id));
  }

  /**
   * Provides test data sets for testSuggesterDependency().
   *
   * @return array[]
   *   An array of argument arrays for testSuggesterDependency().
   *
   * @see \Drupal\Tests\search_api_autocomplete\Kernel\DependencyRemovalTest::testSuggesterDependency()
   */
  public static function suggesterDependencyDataProvider(): array {
    return [
      'soft dependency, one suggester' => [TRUE, FALSE],
      'hard dependency, one suggester' => [FALSE, FALSE],
      'soft dependency, two suggesters' => [TRUE, TRUE],
      'hard dependency, two suggesters' => [FALSE, TRUE],
    ];
  }

}
