<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests for ECA condition eca_list_contains plugin.
 *
 * @group eca
 * @group eca_base
 */
class ListContainsTest extends KernelTestBase {

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * The Condition Plugin Manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(static::$modules);
    $this->tokenService = \Drupal::service('eca.token_services');
    $this->conditionPluginManager = \Drupal::service('plugin.manager.eca.condition');
  }

  /**
   * Tests list contains condition by index.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testListContainsByIndex(): void {
    $config = [
      'list_token' => 'list',
      'method' => 'index',
      'value' => 0,
    ];

    $this->tokenService->addTokenData('list', ['a', 'b', 'c']);
    $plugin = $this->conditionPluginManager->createInstance('eca_list_contains', $config);

    $this->assertTrue($plugin->evaluate());
  }

  /**
   * Tests list contains condition by value.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testListContainsByValue(): void {

    $config = [
      'list_token' => 'list',
      'method' => 'value',
      'value' => 'd',
    ];

    $this->tokenService->addTokenData('list', ['a', 'b', 'c']);
    $plugin = $this->conditionPluginManager->createInstance('eca_list_contains', $config);
    $this->assertFalse($plugin->evaluate());

    $config = [
      'list_token' => 'list',
      'method' => 'value',
      'value' => '[theValue]',
    ];
    $this->tokenService->addTokenData('theValue', 'a');
    $plugin = $this->conditionPluginManager->createInstance('eca_list_contains', $config);
    $this->assertTrue($plugin->evaluate());
  }

  /**
   * Tests list contains condition by value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testListContainsReferencedFields(): void {
    $this->createField();
    $this->prepareNodes();
    $config = [
      'list_token' => '[node1:field_node_multi]',
      'method' => 'value',
      'value' => '[node2]',
    ];
    $plugin = $this->conditionPluginManager->createInstance('eca_list_contains', $config);
    $this->assertTrue($plugin->evaluate());

    $config = [
      'list_token' => '[node1:field_node_multi]',
      'method' => 'value',
      'value' => '[node4]',
    ];
    $plugin = $this->conditionPluginManager->createInstance('eca_list_contains', $config);
    $this->assertFalse($plugin->evaluate());
  }

  /**
   * Creates an entity reference field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createField(): void {
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    node_add_body_field($node_type);

    $field_definition = FieldStorageConfig::create([
      'field_name' => 'field_node_multi',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_definition->save();
    $field = FieldConfig::create([
      'field_storage' => $field_definition,
      'label' => 'A multi-valued entity reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $field->save();
  }

  /**
   * Prepare three nodes to test.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  private function prepareNodes(): void {
    $node1 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);

    $node2 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $node2->save();

    $node3 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $node3->save();

    $node4 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $node4->save();

    $node1->get('field_node_multi')->setValue([$node2, $node3]);
    $node1->save();

    $this->tokenService->addTokenData('node1', $node1);
    $this->tokenService->addTokenData('node2', $node2);
    $this->tokenService->addTokenData('node3', $node3);
    $this->tokenService->addTokenData('node4', $node4);
  }

}
