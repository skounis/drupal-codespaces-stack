<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Tests the entity value changed condition.
 *
 * @group eca
 * @group eca_content
 */
class EntityFieldValueChangedTest extends KernelTestBase {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The condition manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * The entity field value changed condition.
   *
   * @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged|null
   */
  protected ?EntityFieldValueChanged $condition;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);

    // Create a boolean field.
    FieldStorageConfig::create([
      'field_name' => 'field_boolean_test',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_boolean_test',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'A Boolean field.',
      'required' => TRUE,
      'settings' => [
        'on_label' => 'on',
        'off_label' => 'off',
      ],
    ])->save();

    // Create a base_field_override.
    \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('node')['status']->getConfig('article')->save();

    $this->node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => 'First article',
      'field_boolean_test' => 0,
      'status' => 0,
    ]);
    $this->node->save();

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($this->node->getEntityTypeId());
    $this->node->original = $storage->loadUnchanged($this->node->id());
  }

  /**
   * Tests an entity, where the title has changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testValueChanged(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'title',
    ]);

    $this->node->setTitle('Changed title');
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'title.value',
    ]);
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the Boolean field has changed without strict data.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBooleanValueChangedNoStrictDataTypes(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'field_boolean_test',
    ]);

    $this->node->field_boolean_test = 1;
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'field_boolean_test.value',
    ]);
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'status',
    ]);

    $this->node->setUnpublished();
    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the title has changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testNoTokenFound(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => '[custom_token]',
    ]);
    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the title has changed, but with negation.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testValueChangedWithNegation(): void {
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'title',
      'negate' => 'yes',
    ]);

    $this->node->setTitle('Changed title');
    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the Boolean field has changed, but with negation.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBooleanValueChangedNoStrictDataTypesWithNegation(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'field_boolean_test',
      'negate' => 'yes',
    ]);

    $this->node->field_boolean_test = 1;
    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the title has not changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testNoValueChanged(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'title',
    ]);

    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests an entity, where the original property is missing.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testNoOriginalProperty(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'title',
    ]);

    $this->node->setTitle('Changed title');
    $this->node->original = NULL;
    $this->condition->setContextValue('entity', $this->node);
    $this->assertFalse($this->condition->evaluate());
  }

  /**
   * Tests a multi value field, that changed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testMultiFieldValueChanged(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_string_multi',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string_multi',
      'label' => 'A string field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $string = $this->randomMachineName(32);
    $multiFieldNode = Node::create([
      'type' => 'article',
      'uid' => 2,
      'title' => '123',
      'field_string_multi' => [$string, $string . '2', $string . '3'],
    ]);
    $multiFieldNode->save();

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'field_string_multi',
    ]);

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($multiFieldNode->getEntityTypeId());
    $multiFieldNode->original = $storage->loadUnchanged($multiFieldNode->id());

    $multiFieldNode->set('field_string_multi', [
      $string,
      $string . '6',
      $string . '3',
    ]);
    $this->condition->setContextValue('entity', $multiFieldNode);
    $this->assertTrue($this->condition->evaluate());
  }

  /**
   * Tests, if the body value has changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBodyValueChanged(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'body',
    ]);

    $this->node->set('body', 'new value');
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());
  }

  /**
   * Tests, if a referenced entity field has changed.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testReferencedEntityFieldChanged(): void {
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldValueChanged $condition */
    $this->condition = $this->conditionManager->createInstance('eca_entity_field_value_changed', [
      'field_name' => 'uid',
    ]);

    $this->node->set('uid', 2);
    $this->condition->setContextValue('entity', $this->node);
    $this->assertTrue($this->condition->evaluate());
  }

}
