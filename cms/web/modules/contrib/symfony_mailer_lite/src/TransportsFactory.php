<?php

declare(strict_types = 1);

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\symfony_mailer_lite\Transport\ErrorTransport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\Transports;

/**
 * Repository of mailer transport DSNs.
 */
final class TransportsFactory {

  /**
   * Instance of config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Instance of the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Instance of Event Dispatcher.
   *
   * @var \Psr\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * List of Transport factories for our factory.
   *
   * @var \Symfony\Component\Mailer\Transport\TransportFactoryInterface[]
   */
  protected array $transportFactories = [];

  /**
   * Constructs a new TransportsFactory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Add a transport factory.
   *
   * @param \Symfony\Component\Mailer\Transport\TransportFactoryInterface $factory
   *   The transport factory being added.
   */
  public function addTransportFactory(TransportFactoryInterface $factory) {
    $this->transportFactories[] = $factory;
  }

  /**
   * Gets all available transport factories.
   *
   * @return Symfony\Component\Mailer\Transport\TransportFactoryInterface[]
   *   Array with available transport factories.
   */
  public function getTransportFactories(): array {
    return array_merge(
      $this->transportFactories,
      iterator_to_array(Transport::getDefaultFactories($this->eventDispatcher))
    );
  }

  /**
   * Get the collection of all transports.
   *
   * An object must always be returned, even if transport configuration is
   * invalid or missing transports.
   */
  public function create(): Transports {
    /** @var \Drupal\symfony_mailer_lite\Entity\Transport[] $transportConfigs */
    $transports = new Transport($this->getTransportFactories());
    $transportConfigs = $this->entityTypeManager->getStorage('symfony_mailer_lite_transport')->loadMultiple();
    $transportConfigs = array_filter($transportConfigs, fn (Entity\Transport $transportConfig): bool => $transportConfig->status() === TRUE);
    $dsns = array_map(fn (Entity\Transport $transportConfig): string => $transportConfig->getDsn(), $transportConfigs);

    // The default transport must always be the first.
    // @see \Symfony\Component\Mailer\Transport\Transports::__construct
    $defaultTransportId = $this->configFactory->get('symfony_mailer_lite.settings')->get('default_transport');
    if (isset($dsns[$defaultTransportId])) {
      $defaultDsn = $dsns[$defaultTransportId];
      unset($dsns[$defaultTransportId]);
      // Unshift the default with key to the front of the DSN list.
      $dsns = [$defaultTransportId => $defaultDsn] + $dsns;
    }
    else {
      // If the default transport mapping no longer exists, unset everything
      // until a new default transport is created and/or nominated.
      $dsns = [];
    }

    // If nothing was configured, create a null transport.
    if (count($dsns) === 0) {
      return new Transports([
        'error' => new ErrorTransport((string) t('No transports configured. Please configure at least one transport.')),
      ]);
    }

    try {
      return $transports->fromStrings($dsns);
    }
    catch (\Exception $e) {
      // A Transports object with at least one transport must be returned.
      return new Transports([
        'error' => new ErrorTransport((string) t('Error creating transports: @message', ['@message' => $e->getMessage()])),
      ]);
    }
  }

}
