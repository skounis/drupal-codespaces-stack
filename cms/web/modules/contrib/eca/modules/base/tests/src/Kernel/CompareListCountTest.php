<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\Token\TokenInterface;

/**
 * Kernel tests for the "eca_count" condition plugin.
 *
 * @group eca
 * @group eca_base
 */
class CompareListCountTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_base',
  ];

  /**
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests list item count comparison.
   *
   * @dataProvider listDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testListItemCountValues($list, $right, $operator, $negate, $message, $assertTrue = TRUE): void {
    $this->tokenService->addTokenData('list', $list);
    // Configure default settings for condition.
    $config = [
      'left' => 'list',
      'right' => $right,
      'operator' => $operator,
      'type' => StringComparisonBase::COMPARE_TYPE_NUMERIC,
      'negate' => $negate,
    ];
    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ListCountComparison $condition */
    $condition = $this->conditionManager->createInstance('eca_count', $config);
    if ($assertTrue) {
      $this->assertTrue($condition->evaluate(), $message);
    }
    else {
      $this->assertFalse($condition->evaluate(), $message);
    }
  }

  /**
   * Provides multiple test cases for the testListItemCountValues method.
   *
   * @return array
   *   The list item count test cases.
   */
  public static function listDataProvider(): array {
    return [
      [
        ['a', 'b', 'c'],
        '3',
        StringComparisonBase::COMPARE_EQUALS,
        FALSE,
        '3 and 3 are equal.',
      ],
      [
        ['a', 'b', 'c'],
        '2',
        StringComparisonBase::COMPARE_GREATERTHAN,
        FALSE,
        '3 is greater than 2.',
      ],
      [
        'no array',
        '1',
        StringComparisonBase::COMPARE_LESSTHAN,
        FALSE,
        '1 is greater than 0, compared with a string.',
      ],
      [
        [],
        '1',
        StringComparisonBase::COMPARE_LESSTHAN,
        FALSE,
        '1 is greater than 0, compared with an empty list.',
      ],
      [
        NULL,
        '1',
        StringComparisonBase::COMPARE_LESSTHAN,
        FALSE,
        '1 is greater than 0, compared with NULL.',
      ],
      [
        ['a', 'b', 'c'],
        '3',
        StringComparisonBase::COMPARE_EQUALS,
        TRUE,
        '3 and 3 are not unequal.',
        FALSE,
      ],
    ];
  }

}
