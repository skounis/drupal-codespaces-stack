<?php

namespace Drupal\scheduler\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\scheduler\SchedulerManager;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

/**
 * Drush 9 Scheduler commands for Drupal Core 8.4+.
 */
class SchedulerCommands extends DrushCommands {

  /**
   * The Scheduler manager service.
   *
   * @var \Drupal\scheduler\SchedulerManager
   */
  protected $schedulerManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * SchedulerCommands constructor.
   *
   * @param \Drupal\scheduler\SchedulerManager $schedulerManager
   *   Scheduler manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(SchedulerManager $schedulerManager, MessengerInterface $messenger) {
    parent::__construct();
    $this->schedulerManager = $schedulerManager;
    $this->messenger = $messenger;
  }

  /**
   * Lightweight cron to process Scheduler module tasks.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option nomsg
   *   to avoid the "cron completed" message being written to the terminal.
   * @option nolog
   *   to override the site setting and not write 'started' and 'completed'
   *   messages to the dblog.
   *
   * @command scheduler:cron
   * @aliases scheduler-cron, sch-cron
   */
  public function cron(array $options = ['nomsg' => NULL, 'nolog' => NULL]) {
    $this->schedulerManager->runLightweightCron($options);

    $options['nomsg'] ? NULL : $this->messenger->addMessage(dt('Scheduler lightweight cron completed.'));
  }

  /**
   * Entity Update - add Scheduler fields for entities covered by plugins.
   *
   * Use the standard drush parameter -q for quiet mode (no terminal output).
   *
   * @command scheduler:entity-update
   * @aliases scheduler-entity-update, sch-ent-upd, sch-upd
   */
  public function entityUpdate() {
    $result = $this->schedulerManager->entityUpdate();
    $updated = $result ? implode(', ', $result) : dt('nothing to update');
    $this->messenger->addMessage(dt('Scheduler entity update - @updated', ['@updated' => $updated]));
  }

  /**
   * Entity Revert - remove Scheduler fields and third-party-settings.
   *
   * Use the standard drush parameter -q for quiet mode (no terminal output).
   *
   * @option types A comma-delimited list of entity type ids. Default is all
   *    entity types that need reverting.
   *
   * @command scheduler:entity-revert
   * @aliases scheduler-entity-revert, sch-ent-rev, sch-rev
   */
  public function entityRevert(array $options = ['types' => '']) {
    $result = $this->schedulerManager->entityRevert(StringUtils::csvToArray($options['types']));
    $reverted = $result ? implode(', ', $result) : dt('nothing to do');
    $this->messenger->addMessage(dt('Scheduler entity revert - @reverted', ['@reverted' => $reverted]));
  }

}
