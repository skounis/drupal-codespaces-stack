<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_entity_field_value_empty" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class EntityFieldValueEmptyTest extends KernelTestBase {

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
    'filter',
    'text',
    'node',
    'eca',
    'eca_content',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);
    // Create a multi-value text field.
    FieldStorageConfig::create([
      'field_name' => 'field_string_multi',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string_multi',
      'label' => 'A string field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    // Create a single-value entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_content',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'module' => 'core',
      'cardinality' => 1,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'label' => 'A single-value node reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_storage' => $field_storage,
    ])->save();

    // Create a multi-value entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_content_multi',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'module' => 'core',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'label' => 'A multi-value node reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_storage' => $field_storage,
    ])->save();
  }

  /**
   * Tests EntityFieldValueEmpty on a node.
   */
  public function testEntityFieldValueEmpty(): void {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');

    $string = $this->randomMachineName(32);
    $text = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);

    $node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => '123',
      'body' => ['summary' => $summary, 'value' => $text],
      'field_string_multi' => [$string, $string . '2', $string . '3'],
    ]);
    $node->save();

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueEmpty $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_value_empty', [
      'field_name' => 'body',
      'negate' => FALSE,
    ]);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate());

    $node->get('body')->setValue(NULL);
    $this->assertTrue($condition->evaluate());

    // Now test for entity references.
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueEmpty $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_value_empty', [
      'field_name' => 'field_content_multi',
      'negate' => FALSE,
    ]);
    $condition->setContextValue('entity', $node);

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueEmpty $condition_item */
    $condition_item = $condition_manager->createInstance('eca_entity_field_value_empty', [
      'field_name' => 'field_content_multi.0',
      'negate' => FALSE,
    ]);
    $condition_item->setContextValue('entity', $node);

    $another_node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => '456',
      'body' => ['summary' => $summary, 'value' => $text],
      'field_string_multi' => [$string, $string . '4', $string . '5'],
    ]);
    $another_node->save();

    $this->assertTrue($condition->evaluate());
    $this->assertTrue($condition_item->evaluate());
    $node->get('field_content_multi')->setValue($another_node);
    $this->assertFalse($condition->evaluate());
    $this->assertFalse($condition_item->evaluate());
    $node->save();
    $this->assertFalse($condition->evaluate());
    $this->assertFalse($condition_item->evaluate());

    $another_node->delete();
    $this->assertTrue($condition->evaluate());
    $this->assertTrue($condition_item->evaluate());

  }

}
