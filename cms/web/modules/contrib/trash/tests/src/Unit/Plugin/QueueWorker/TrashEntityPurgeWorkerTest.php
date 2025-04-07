<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Unit\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use Drupal\trash\Plugin\QueueWorker\TrashEntityPurgeWorker;
use Drupal\trash\TrashManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit test for the entity purge worker.
 *
 * @coversDefaultClass \Drupal\trash\Plugin\QueueWorker\TrashEntityPurgeWorker
 * @group trash
 */
class TrashEntityPurgeWorkerTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface|MockObject $entityTypeManager;

  /**
   * The trash manager.
   *
   * @var \Drupal\trash\TrashManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected TrashManagerInterface|MockObject $trashManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelFactoryInterface|MockObject $loggerFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected TimeInterface|MockObject $time;

  /**
   * The trash purge worker under test.
   *
   * @var \Drupal\trash\Plugin\QueueWorker\TrashEntityPurgeWorker
   */
  protected TrashEntityPurgeWorker $worker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $configuration = [];
    $plugin_id = 'trash_entity_purge';
    $plugin_definition = [
      'id' => $plugin_id,
    ];
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->trashManager = $this->createMock(TrashManagerInterface::class);
    $this->trashManager->expects($this->any())
      ->method('getEnabledEntityTypes')
      ->willReturn(['node', 'media']);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->worker = new TrashEntityPurgeWorker($configuration, $plugin_id, $plugin_definition, $this->entityTypeManager, $this->trashManager, $this->loggerFactory, $this->time);
    $this->worker->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItem() {
    $logger = $this->createMock(LoggerInterface::class);
    $this->loggerFactory->expects($this->once())
      ->method('get')
      ->with('trash')
      ->willReturn($logger);
    $logger->expects($this->once())
      ->method('info')
      ->with('Successfully purged 2 content items');

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('addMetaData')
      ->with('trash', 'inactive')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('nid', ['23', '1337'], 'IN')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn(['23', '1337']);

    $nodeStorage = $this->createMock(ContentEntityStorageInterface::class);
    $nodeDefinition = $this->createMock(EntityTypeInterface::class);
    $nodeDefinition->expects($this->once())
      ->method('getKey')
      ->with('id')
      ->willReturn('nid');
    $nodeStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($nodeStorage);
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('node')
      ->willReturn($nodeDefinition);
    $nodeDefinition->expects($this->once())
      ->method('getSingularLabel')
      ->willReturn('content item');
    $nodeDefinition->expects($this->once())
      ->method('getPluralLabel')
      ->willReturn('content items');

    $nodes = [
      '23' => $this->createMock(NodeInterface::class),
      '1337' => $this->createMock(NodeInterface::class),
    ];
    $this->trashManager->expects($this->once())
      ->method('executeInTrashContext')
      ->with('inactive', /* We can't test the closure :-( */);

    $this->worker->processItem([
      'batch' => ['23', '1337'],
      'entity_type_id' => 'node',
    ]);
  }

}
