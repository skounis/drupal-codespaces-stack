<?php

namespace Drupal\eca_base;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Event\BaseHookHandler;
use Drupal\eca\Event\TriggerEvent;

/**
 * The handler for hook implementations within the eca_base.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * The ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected EcaState $state;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The HookHandler constructor.
   *
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The service for triggering ECA-related events.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(TriggerEvent $trigger_event, DateFormatterInterface $dateFormatter, LoggerChannelInterface $logger) {
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    parent::__construct($trigger_event);
  }

  /**
   * Set the ECA state service.
   *
   * @param \Drupal\eca\EcaState $state
   *   The ECA state service.
   */
  public function setState(EcaState $state): void {
    $this->state = $state;
  }

  /**
   * Trigger ECA's cron event.
   */
  public function cron(): void {
    $this->triggerEvent->dispatchFromPlugin('eca_base:eca_cron', $this->state, $this->dateFormatter, $this->logger);
  }

}
