<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * The compare field value test.
 *
 * <p>Kernel tests for the "eca_entity_field_value" and
 * "eca_entity_original_field_value" condition plugins.</p>
 *
 * @group eca
 * @group eca_content
 */
class CompareFieldValueTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'eca',
    'eca_content',
  ];

  /**
   * The condition manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $this->node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => 'First article',
    ]);
    $this->node->save();
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
  }

  /**
   * Tests single string field comparison.
   *
   * @dataProvider fieldValueDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function testNodeTitle(string $field_value, string $operator, string $message): void {
    $config = [
      'expected_value' => $field_value,
      'operator' => $operator,
      'field_name' => 'title',
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_entity_field_value', $config);
    $condition->setContextValue('entity', $this->node);
    $this->assertTrue($condition->evaluate(), $message);

    // Additionally test when accessing a field property.
    $config = [
      'expected_value' => $field_value,
      'operator' => $operator,
      'field_name' => 'title.value',
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_entity_field_value', $config);
    $condition->setContextValue('entity', $this->node);
    $this->assertTrue($condition->evaluate(), $message);

    // Additionally test negation.
    $config = [
      'expected_value' => $field_value,
      'operator' => $operator,
      'field_name' => 'title',
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => TRUE,
    ];
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_entity_field_value', $config);
    $condition->setContextValue('entity', $this->node);
    $this->assertFalse($condition->evaluate(), $message);
  }

  /**
   * Tests the original title method.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function testNodeOriginalTitle(): void {
    $modifiedTitle = 'Modified title';
    $config = [
      'field_name' => 'title',
      'expected_value' => $modifiedTitle,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    $this->node
      ->setTitle($modifiedTitle)
      ->save();

    // Test modified node title.
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_entity_field_value', $config);
    $condition->setContextValue('entity', $this->node);
    $this->assertTrue($condition->evaluate(), 'Node title should be modified.');

    // Test original node title.
    $condition = $this->conditionManager->createInstance('eca_entity_original_field_value', $config);
    $condition->setContextValue('entity', $this->node);
    $this->assertFalse($condition->evaluate(), 'Original node title should not be modified.');
  }

  /**
   * Provides multiple string test cases for the testScalarValues method.
   *
   * @return array
   *   The string test cases.
   */
  public static function fieldValueDataProvider(): array {
    return [
      [
        'First article',
        StringComparisonBase::COMPARE_EQUALS,
        'Title equals expected value.',
      ],
      [
        'First',
        StringComparisonBase::COMPARE_BEGINS_WITH,
        'Title begins with expected value.',
      ],
      [
        'article',
        StringComparisonBase::COMPARE_ENDS_WITH,
        'Title ends with expected value.',
      ],
      [
        't a',
        StringComparisonBase::COMPARE_CONTAINS,
        'Title contains expected value.',
      ],
      [
        'An article',
        StringComparisonBase::COMPARE_GREATERTHAN,
        'Title is greater than expected value.',
      ],
      [
        'Second article',
        StringComparisonBase::COMPARE_LESSTHAN,
        'Title is less than expected value.',
      ],
      [
        'First article',
        StringComparisonBase::COMPARE_ATMOST,
        'Title is at most the equal expected value.',
      ],
      [
        'Second article',
        StringComparisonBase::COMPARE_ATMOST,
        'Title is at most expected value.',
      ],
      [
        'First article',
        StringComparisonBase::COMPARE_ATLEAST,
        'Title is at least the equal expected value.',
      ],
      [
        'An article',
        StringComparisonBase::COMPARE_ATLEAST,
        'Title is at least expected value.',
      ],
    ];
  }

}
