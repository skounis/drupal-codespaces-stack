<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\PluginManager\Event as EventPluginManager;
use Drupal\eca\Processor;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for the ECA processor engine.
 *
 * @group eca
 * @group eca_core
 */
class ProcessorTest extends EcaUnitTestBase {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The ECA event plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected EventPluginManager $eventPluginManager;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->tokenService = $this->createMock(TokenInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->eventPluginManager = $this->createMock(EventPluginManager::class);
    $this->state = $this->createMock(StateInterface::class);
  }

  /**
   * Tests the recursionThresholdSurpassed without history.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdWithoutHistory(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, 3);
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $eca = $this->getEca('1');
    $result = $method->invokeArgs($processor, [
      $eca,
      $this->getEcaEvent($eca, '1'),
    ]);
    $this->assertFalse($result);
  }

  /**
   * Tests the recursionThreshold is surpassed.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdSurpassed(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, 2);
    $this->assertTrue($this->isThresholdComplied($processor));
  }

  /**
   * Tests the recursionThreshold is not surpassed.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdNotSurpassed(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, 3);
    $this->assertFalse($this->isThresholdComplied($processor));
  }

  /**
   * Check whether the threshold is complied.
   *
   * @param \Drupal\eca\Processor $processor
   *   The ECA processor service.
   *
   * @return bool
   *   Returns TRUE, if the recursion threshold got exceeded, FALSE otherwise.
   *
   * @throws \ReflectionException
   */
  private function isThresholdComplied(Processor $processor): bool {
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $executionHistory = $this->getPrivateProperty(Processor::class, 'executionHistory');
    $eca = $this->getEca('1');
    $ecaEvent = $this->getEcaEvent($eca, '1');
    $ecaEventHistory = [];
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $this->getEcaEvent($eca, '2')->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $this->getEcaEvent($eca, '3')->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $executionHistory->setValue($processor, $ecaEventHistory);
    return $method->invokeArgs($processor, [$eca, $ecaEvent]);
  }

  /**
   * Gets an ECA config entity initialized with mocks.
   *
   * @param string $id
   *   The ID of the ECA config entity.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The mocked ECA config entity.
   */
  private function getEca(string $id): Eca {
    $eca = $this->createMock(Eca::class);
    $eca->set('id', $id);
    $eca->method('id')->willReturn($id);
    return $eca;
  }

  /**
   * Gets a EcaEvent initialized with mocks.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   An ECA config entity.
   * @param string $id
   *   The ID of the event.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent
   *   The mocked event.
   */
  private function getEcaEvent(Eca $eca, string $id): EcaEvent {
    $event = $this->createMock(EventInterface::class);
    return new EcaEvent($eca, $id, 'label', $event);
  }

}
