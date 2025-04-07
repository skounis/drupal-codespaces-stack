<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\trash\TrashEntityPurger;
use Drupal\trash\TrashManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit test for the entity purger.
 *
 * @coversDefaultClass \Drupal\trash\TrashEntityPurger
 * @group trash
 */
class TrashEntityPurgerTest extends UnitTestCase {

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface|MockObject $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected TimeInterface|MockObject $time;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected QueueFactory|MockObject $queueFactory;

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings|\PHPUnit\Framework\MockObject\MockObject
   */
  protected Settings|MockObject $settings;

  /**
   * The trash purger under test.
   *
   * @var \Drupal\trash\TrashEntityPurger
   */
  protected TrashEntityPurger $purger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->trashManager = $this->createMock(TrashManagerInterface::class);
    $this->trashManager->expects($this->any())
      ->method('getEnabledEntityTypes')
      ->willReturn(['node', 'media']);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->queueFactory = $this->createMock(QueueFactory::class);
    $this->settings = new Settings(['entity_update_batch_size' => 50]);
    $this->purger = new TrashEntityPurger($this->entityTypeManager, $this->trashManager, $this->configFactory, $this->time, $this->queueFactory, $this->settings);
  }

  /**
   * @covers ::cronPurge
   */
  public function testNothingIsQueuedOnCronIfDisabled() {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('auto_purge.enabled')
      ->willReturn(FALSE);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('trash.settings')
      ->willReturn($config);
    $this->time->expects($this->any())
      ->method('getCurrentTime')
      ->willReturn(1_000_000);
    $this->queueFactory->expects($this->never())
      ->method('get');

    $this->purger->cronPurge();
  }

  /**
   * @covers ::cronPurge
   */
  public function testEntitiesAreQueuedOnCron() {
    $config = $this->createMock(Config::class);

    $callSequence = [
      'auto_purge.enabled',
      'auto_purge.after',
      'auto_purge.after',
    ];
    $config->expects($this->exactly(count($callSequence)))
      ->method('get')
      ->with($this->callback(function ($value) use (&$callSequence) {
        return array_shift($callSequence) === $value;
      }))
      ->willReturnOnConsecutiveCalls(TRUE, '24 hours', '24 hours');

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('trash.settings')
      ->willReturn($config);
    $this->time->expects($this->any())
      ->method('getCurrentTime')
      ->willReturn(1_000_000);
    $queue = $this->createMock(QueueInterface::class);
    $this->queueFactory->expects($this->exactly(2))
      ->method('get')
      ->with(TrashEntityPurger::PURGE_QUEUE_NAME)
      ->willReturn($queue);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->exactly(2))
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $query->expects($this->exactly(2))
      ->method('addMetaData')
      ->with('trash', 'inactive')
      ->willReturnSelf();
    $query->expects($this->exactly(2))
      ->method('condition')
      ->with('deleted', 1_000_000 - 3_600 * 24, '<')
      ->willReturnSelf();
    $query->expects($this->exactly(2))
      ->method('execute')
      ->willReturnOnConsecutiveCalls(['23', '1337'], ['21']);

    $nodeStorage = $this->createMock(ContentEntityStorageInterface::class);
    $mediaStorage = $this->createMock(ContentEntityStorageInterface::class);
    $nodeStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $mediaStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturnCallback(function ($entity_type_id) use ($nodeStorage, $mediaStorage) {
        return match ($entity_type_id) {
          'node' => $nodeStorage,
          'media' => $mediaStorage,
          default => NULL,
        };
      });

    $items = [
      [
        'batch' => ['23', '1337'],
        'entity_type_id' => 'node',
      ],
      [
        'batch' => ['21'],
        'entity_type_id' => 'media',
      ],
    ];
    $queue->expects($this->exactly(count($items)))
      ->method('createItem')
      ->with($this->callback(function ($item) use (&$items) {
        return array_shift($items) === $item;
      }));

    $this->purger->cronPurge();
  }

}
