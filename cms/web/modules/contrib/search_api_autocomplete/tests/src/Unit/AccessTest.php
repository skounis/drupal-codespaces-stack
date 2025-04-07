<?php

namespace Drupal\Tests\search_api_autocomplete\Unit;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Utility\AutocompleteHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Tests access to the autocomplete path.
 *
 * @group search_api_autocomplete
 *
 * @coversDefaultClass \Drupal\search_api_autocomplete\Utility\AutocompleteHelper
 */
class AccessTest extends UnitTestCase {

  /**
   * The autocomplete helper object used for the test.
   *
   * @var \Drupal\search_api_autocomplete\Utility\AutocompleteHelperInterface
   */
  protected $autocompleteHelper;

  /**
   * The search entity used in this test.
   *
   * @var \Drupal\search_api_autocomplete\SearchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $search;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->autocompleteHelper = new AutocompleteHelper();
    $this->search = $this->createMock(SearchInterface::class);
    $this->search->method('id')->willReturn('test');
    $this->search->method('getCacheContexts')->willReturn(['test']);
    $this->search->method('getCacheTags')->willReturn(['test']);
    $this->search->method('getCacheMaxAge')->willReturn(1337);

    // \Drupal\Core\Access\AccessResult::addCacheContexts() will need the cache
    // contexts manager service for validation.
    $container = new ContainerBuilder();
    $contexts = ['test', 'user.permissions'];
    $cacheContextsManager = new CacheContextsManager($container, $contexts);
    $container->set('cache_contexts_manager', $cacheContextsManager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests access to the autocomplete path under a given set of conditions.
   *
   * @param array $options
   *   Associative array of options, containing one or more of the following:
   *   - status: Whether the search should be enabled.
   *   - index: Whether the search's index should exist.
   *   - index_status: Whether the search's index should be enabled.
   *   - permission: Whether the user should have the necessary permission to
   *     access the search.
   *   - admin: Whether the user should have the "administer
   *     search_api_autocomplete" permission.
   *   All options default to TRUE.
   * @param bool $should_be_allowed
   *   Whether access should be allowed.
   *
   * @covers ::access
   *
   * @dataProvider accessTestDataProvider
   */
  public function testAccess(array $options, $should_be_allowed) {
    $options += [
      'status' => TRUE,
      'index' => TRUE,
      'index_status' => TRUE,
      'permission' => TRUE,
      'admin' => TRUE,
    ];

    $this->search->method('status')->willReturn($options['status']);
    $this->search->method('hasValidIndex')->willReturn($options['index']);
    if ($options['index']) {
      $index = $this->createMock(IndexInterface::class);
      $index->method('status')->willReturn($options['index_status']);
      $this->search->method('getIndex')->willReturn($index);
    }

    /** @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject $account */
    $account = $this->createMock(AccountInterface::class);
    $permission = 'use search_api_autocomplete for ' . $this->search->id();
    $account->method('hasPermission')->willReturnMap([
      [$permission, $options['permission']],
      ['administer search_api_autocomplete', $options['admin']],
    ]);

    // Needn't really be AccessResultNeutral, of course, but this is the easiest
    // way to get all the possible interfaces.
    /** @var \Drupal\Core\Access\AccessResultNeutral $result */
    $result = $this->autocompleteHelper->access($this->search, $account);
    $this->assertEquals($should_be_allowed, $result->isAllowed());
    $this->assertEquals(FALSE, $result->isForbidden());
    $this->assertEquals(!$should_be_allowed, $result->isNeutral());

    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('test', $result->getCacheContexts());
    $this->assertContains('test', $result->getCacheTags());
    $this->assertEquals(1337, $result->getCacheMaxAge());

    if (!$should_be_allowed) {
      $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
      $this->assertEquals("The \"$permission\" permission is required and autocomplete for this search must be enabled.", $result->getReason());
    }
  }

  /**
   * Provides test data for the testAccess() method.
   *
   * @return array
   *   An array containing arrays of method arguments for testAccess().
   *
   * @see \Drupal\Tests\search_api_autocomplete\Unit\AccessTest::testAccess
   */
  public static function accessTestDataProvider(): array {
    return [
      'search disabled' => [
        ['status' => FALSE],
        FALSE,
      ],
      'index does not exist' => [
        ['index' => FALSE],
        FALSE,
      ],
      'index disabled' => [
        ['index_status' => FALSE],
        FALSE,
      ],
      'search-specific permission missing' => [
        ['permission' => FALSE, 'admin' => FALSE],
        FALSE,
      ],
      'search-specific permission present' => [
        ['admin' => FALSE],
        TRUE,
      ],
      'is admin' => [
        ['permission' => FALSE],
        TRUE,
      ],
    ];
  }

}
