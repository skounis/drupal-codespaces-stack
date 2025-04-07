<?php

namespace Drupal\Tests\search_api_exclude\Unit\Plugin\Processor;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemList;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_exclude\Plugin\search_api\processor\NodeExclude;
use Drupal\Tests\PhpunitCompatibilityTrait;
use Drupal\Tests\search_api\Unit\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Node exclude" processor.
 *
 * @group search_api_exclude
 *
 * @var \Drupal\search_api_exclude\Plugin\search_api\processor\NodeExclude
 */
class NodeExcludeTest extends UnitTestCase {

  use PhpunitCompatibilityTrait;
  use TestItemsTrait;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\search_api_exclude\Plugin\search_api\processor\NodeExclude
   */
  protected $processor;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $index;

  /**
   * The test index's potential datasources.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface[]
   */
  protected $datasources = [];

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();
    // Include system.module in order to load some required constants.
    require_once sprintf('%s/core/modules/system/system.module', $this->root);

    $this->setUpMockContainer();

    $this->processor = new NodeExclude([], 'node_exclude', []);
    $this->index = $this->createMock(IndexInterface::class);

    foreach (['node', 'comment', 'user'] as $entity_type) {
      $datasource = $this->createMock(DatasourceInterface::class);
      $datasource->expects($this->any())
        ->method('getEntityTypeId')
        ->will($this->returnValue($entity_type));
      $this->datasources[sprintf('entity:%s', $entity_type)] = $datasource;
    }
  }

  /**
   * Tests whether supportsIndex() returns TRUE for an index containing nodes.
   *
   * @param string[]|null $datasource_ids
   *   The IDs of datasources the index should have, or NULL if it should have
   *   all of them.
   * @param bool $expected
   *   Whether the processor is supposed to support that index.
   *
   * @dataProvider supportsIndexDataProvider
   */
  public function testSupportsIndex(array $datasource_ids = NULL, $expected) {
    if ($datasource_ids !== NULL) {
      $datasource_ids = array_flip($datasource_ids);
      $this->datasources = array_intersect_key($this->datasources, $datasource_ids);
    }
    $this->index->method('getDatasources')
      ->will($this->returnValue($this->datasources));
    $this->assertEquals($expected, NodeExclude::supportsIndex($this->index));
  }

  /**
   * Provides data for the testSupportsIndex() test.
   *
   * @return array
   *   Array of parameter arrays for testSupportsIndex().
   */
  public function supportsIndexDataProvider() {
    return [
      'node datasource' => [['entity:node'], TRUE],
      'comment datasource' => [['entity:comment'], FALSE],
      'user datasource' => [['entity:user'], FALSE],
    ];
  }

  /**
   * Tests if nodes, which are configured to be excluded, are removed.
   */
  public function testAlterItems() {
    $datasource_id = 'entity:node';
    /** @var \Drupal\search_api\Utility\FieldsHelper $fields_helper */
    $fields_helper = \Drupal::service('search_api.fields_helper');
    $items = [];

    foreach ([1 => '1', 2 => '0', 3 => NULL] as $i => $exclude) {
      $item_id = Utility::createCombinedId($datasource_id, sprintf('%d:en', $i));
      $item = $fields_helper->createItem($this->index, $item_id, $this->datasources[$datasource_id]);

      /** @var \Drupal\Core\Entity\ContentEntityInterface $node */
      $item->setOriginalObject(EntityAdapter::createFromEntity($this->createNode($exclude)));
      $items[$item_id] = $item;
    }

    $this->processor->alterIndexedItems($items);
    $expected = [
      Utility::createCombinedId($datasource_id, '2:en'),
      Utility::createCombinedId($datasource_id, '3:en'),
    ];
    $this->assertEquals($expected, array_keys($items));
  }

  /**
   * Creates a node for testing.
   *
   * @param mixed $exclude
   *   The value of the sae_exclude-field.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mocked node.
   */
  private function createNode($exclude) {
    $nodeType = $this->getMockBuilder(NodeType::class)
      ->disableOriginalConstructor()
      ->getMock();
    $nodeType->method('getThirdPartySetting')
      ->with('search_api_exclude', 'enabled', FALSE)
      ->will($this->returnValue(TRUE));
    $entityReferenceList = $this->getMockBuilder(EntityReferenceFieldItemList::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entityReferenceList->method('__get')
      ->with('entity')
      ->will($this->returnValue($nodeType));

    $field_item_list = $this->getMockBuilder(FieldItemList::class)
      ->disableOriginalConstructor()
      ->getMock();
    $field_item_list->method('getString')
      ->will($this->returnValue($exclude));

    $node = $this->getMockBuilder(Node::class)
      ->disableOriginalConstructor()
      ->getMock();
    $node->method('__get')
      ->with('type')
      ->will($this->returnValue($entityReferenceList));
    $node->method('get')
      ->with('sae_exclude')
      ->will($this->returnValue($field_item_list));

    return $node;
  }

}
