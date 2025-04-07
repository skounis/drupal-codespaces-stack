<?php

namespace Drupal\eca_base\Drush\Commands;

use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ECA base Drush command file.
 */
final class EcaBaseCommands extends DrushCommands {

  /**
   * EcaBaseCommand constructor.
   */
  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct();
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\eca_base\Drush\Commands\EcaBaseCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): EcaBaseCommands {
    return new EcaBaseCommands(
      $container->get('event_dispatcher')
    );
  }

  /**
   * Trigger custom event with given event ID.
   */
  #[Command(name: 'eca:trigger:custom_event', aliases: [])]
  #[Argument(name: 'id', description: 'The id of the custom event to be triggered.')]
  #[Usage(name: 'eca:trigger:custom_event myevent', description: 'Trigger custom event with given event ID.')]
  public function triggerCustomEvent(string $id): void {
    $event = new CustomEvent($id, []);
    $this->eventDispatcher->dispatch($event, BaseEvents::CUSTOM);
  }

}
