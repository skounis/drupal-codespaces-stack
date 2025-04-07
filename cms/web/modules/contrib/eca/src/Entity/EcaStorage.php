<?php

namespace Drupal\eca\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Storage handler for ECA configurations.
 */
class EcaStorage extends ConfigEntityStorage {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The dynamic event subscriber.
   *
   * @var \Symfony\Component\EventDispatcher\EventSubscriberInterface
   */
  protected EventSubscriberInterface $eventSubscriber;

  /**
   * The Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): EcaStorage {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    /** @var \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel */
    $loggerChannel = $container->get('logger.channel.eca');
    $instance->setLogger($loggerChannel);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->eventSubscriber = $container->get('eca.dynamic_subscriber');
    $instance->lock = $container->get('lock');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * Rebuilds the state of subscribed events.
   */
  public function rebuildSubscribedEvents(): void {
    $lock_name = 'eca_rebuild_subscribed_events';
    if (!$this->lock->acquire($lock_name)) {
      try {
        $sleep = random_int(1000, 50000);
      }
      catch (\Exception | RandomException) {
        $sleep = 2500;
      }
      usleep($sleep);
      $this->rebuildSubscribedEvents();
      return;
    }

    $subscribedEvents = $this->doRebuildSubscribedEvents();

    if ($this->state->get('eca.subscribed') !== $subscribedEvents) {
      $this->state->set('eca.subscribed', $subscribedEvents);
      $this->eventDispatcher->removeSubscriber($this->eventSubscriber);
      $this->eventDispatcher->addSubscriber($this->eventSubscriber);
    }

    $this->lock->release($lock_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update): void {
    parent::doPostSave($entity, $update);
    $this->rebuildSubscribedEvents();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(array $entities): void {
    parent::delete($entities);
    $this->rebuildSubscribedEvents();
  }

  /**
   * Rebuilds the list of subscribed events based on the current configuration.
   *
   * @return array
   *   The list of ECA configuration IDs, grouped by their subscribed events.
   */
  protected function doRebuildSubscribedEvents(): array {
    $subscribed = [];
    $entities = $this->loadMultiple();
    // Sort the configurations by weight and label.
    uasort($entities, [$this->entityType->getClass(), 'sort']);
    /**
     * @var \Drupal\eca\Entity\Eca $eca
     */
    foreach ($entities as $eca) {
      if (!$eca->status()) {
        continue;
      }
      foreach ($eca->getUsedEvents() as $eca_event_id => $ecaEvent) {
        $eca_id = $eca->id();
        $plugin = $ecaEvent->getPlugin();
        $name = $plugin->eventName();
        $priority = $plugin->subscriberPriority();
        $wildcard = $plugin->generateWildcard($eca_id, $ecaEvent);
        $subscribed[$name][$priority][$eca_id][$eca_event_id] = $wildcard;
      }
    }

    // Make sure that priorities are always distinct.
    foreach ($subscribed as $prioritized) {
      if (count($prioritized) > 1) {
        throw new \LogicException("Priority for event subscription must be distinct.");
      }
    }

    $this->logger->debug('Rebuilt subscribed events of ECA configuration.');
    return $subscribed;
  }

  /**
   * Set the logger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   */
  public function setLogger(LoggerChannelInterface $logger): void {
    $this->logger = $logger;
  }

}
