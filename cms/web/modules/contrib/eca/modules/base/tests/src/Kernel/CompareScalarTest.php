<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;

/**
 * Kernel tests for the "eca_scalar" condition plugin.
 *
 * @group eca
 * @group eca_base
 */
class CompareScalarTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
  }

  /**
   * Tests scalar value comparison.
   *
   * @dataProvider stringDataProvider
   * @dataProvider numericDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testScalarValues($left, $right, $operator, $type, $case, $negate, $message, $assertTrue = TRUE): void {
    // Configure default settings for condition.
    $config = [
      'left' => $left,
      'right' => $right,
      'operator' => $operator,
      'type' => $type,
      'case' => $case,
      'negate' => $negate,
    ];
    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $condition */
    $condition = $this->conditionManager->createInstance('eca_scalar', $config);
    if ($assertTrue) {
      $this->assertTrue($condition->evaluate(), $message);
    }
    else {
      $this->assertFalse($condition->evaluate(), $message);
    }
  }

  /**
   * Provides multiple string test cases for the testScalarValues method.
   *
   * @return array
   *   The string test cases.
   */
  public static function stringDataProvider(): array {
    return [
      [
        'my test string',
        'my test string',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left equals right value.',
      ],
      [
        'my test string',
        'my test string',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        TRUE,
        FALSE,
        'Left equals (case sensitive) right value.',
      ],
      [
        'my test string',
        'My Test String',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        TRUE,
        TRUE,
        'Left does not equal (case sensitive) right value.',
      ],
      [
        'my test string',
        'my test',
        StringComparisonBase::COMPARE_BEGINS_WITH,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left begins with right value.',
      ],
      [
        'my test string',
        'test string',
        StringComparisonBase::COMPARE_ENDS_WITH,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left ends with right value.',
      ],
      [
        'my test string',
        'test',
        StringComparisonBase::COMPARE_CONTAINS,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left contains right value.',
      ],
      [
        'my test string',
        'a test string',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is greater than right value.',
      ],
      [
        'my test string',
        'your test string',
        StringComparisonBase::COMPARE_LESSTHAN,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is less than right value.',
      ],
      [
        'my test string',
        'my test string',
        StringComparisonBase::COMPARE_ATMOST,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is at most the equal right value.',
      ],
      [
        'my test string',
        'your test string',
        StringComparisonBase::COMPARE_ATMOST,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is at most right value.',
      ],
      [
        'my test string',
        'my test string',
        StringComparisonBase::COMPARE_ATLEAST,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is at least the equal right value.',
      ],
      [
        'my test string',
        'a test string',
        StringComparisonBase::COMPARE_ATLEAST,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'Left is at least right value.',
      ],
    ];
  }

  /**
   * Provides multiple integer test cases for the testScalarValues method.
   *
   * @return array
   *   The numeric test cases.
   */
  public static function numericDataProvider(): array {
    return [
      [
        '5',
        '5',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        '5 and 5 are equal.',
      ],
      [
        '5',
        '4',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        '5 is great than 4.',
      ],
      [
        '5.5',
        '5.4',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        '5.5 is great than 5.4.',
      ],
      [
        'a',
        '5',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        'First value should be numeric.',
        FALSE,
      ],
      [
        '5',
        'a',
        StringComparisonBase::COMPARE_EQUALS,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        'First value should be numeric.',
        FALSE,
      ],
      [
        '15',
        '5',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_NUMERIC,
        FALSE,
        FALSE,
        '15 is greater than 5 for numeric comparison.',
      ],
      [
        '15',
        '5',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        '15 is greater than 5 for value comparison.',
      ],
      [
        '15',
        '5',
        StringComparisonBase::COMPARE_LESSTHAN,
        StringComparisonBase::COMPARE_TYPE_LEXICAL,
        FALSE,
        FALSE,
        '15 is smaller than 5 for lexical comparison.',
      ],
      [
        'img15',
        'img5',
        StringComparisonBase::COMPARE_LESSTHAN,
        StringComparisonBase::COMPARE_TYPE_VALUE,
        FALSE,
        FALSE,
        'img15 is smaller than img5 for value comparison.',
      ],
      [
        'img15',
        'img5',
        StringComparisonBase::COMPARE_GREATERTHAN,
        StringComparisonBase::COMPARE_TYPE_NATURAL,
        FALSE,
        FALSE,
        'img15 is greater than img5 for natural comparison.',
      ],
    ];
  }

  /**
   * Tests comparison of replaced tokens.
   */
  public function testTokenComparison(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    /** @var \Drupal\eca_base\Plugin\ECA\Condition\ScalarComparison $condition */
    $condition = $condition_manager->createInstance('eca_scalar', [
      'left' => '[left:value]',
      'right' => '[right:value]',
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => TRUE,
      'negate' => FALSE,
    ]);
    $this->assertFalse($condition->evaluate());

    $token_services->addTokenData('left:value', 'a');
    $token_services->addTokenData('right:value', 'b');
    $this->assertFalse($condition->evaluate());

    $token_services->addTokenData('left:value', 'a');
    $token_services->addTokenData('right:value', 'a');
    $this->assertTrue($condition->evaluate());
  }

}
