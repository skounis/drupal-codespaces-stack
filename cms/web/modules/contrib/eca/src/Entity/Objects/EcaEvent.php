<?php

namespace Drupal\eca\Entity\Objects;

use Drupal\eca\Entity\Eca;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides an ECA item of type event for internal processing.
 */
class EcaEvent extends EcaObject implements ObjectWithPluginInterface {

  /**
   * ECA event plugin.
   *
   * @var \Drupal\eca\Plugin\ECA\Event\EventInterface
   */
  protected EventInterface $plugin;

  /**
   * Event constructor.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $id
   *   The event ID provided by the modeller.
   * @param string $label
   *   The event label.
   * @param \Drupal\eca\Plugin\ECA\Event\EventInterface $plugin
   *   The event plugin.
   */
  public function __construct(Eca $eca, string $id, string $label, EventInterface $plugin) {
    parent::__construct($eca, $id, $label, $this);
    $this->plugin = $plugin;
  }

  /**
   * Get the plugin instance.
   *
   * @return \Drupal\eca\Plugin\ECA\Event\EventInterface
   *   The plugin instance.
   */
  public function getPlugin(): EventInterface {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EcaObject $predecessor, Event $event, array $context): bool {
    if (!parent::execute($predecessor, $event, $context)) {
      return FALSE;
    }
    $this->plugin->setEvent($event);
    return TRUE;
  }

}
