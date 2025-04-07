<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validation;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\ConsoleUpdateStage;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\automatic_updates\UpdateStage;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\package_manager\Event\PostApplyEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Runs status checks and caches the results.
 */
final class StatusChecker implements EventSubscriberInterface {

  use StatusCheckTrait;

  /**
   * The key/value expirable storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  private readonly KeyValueStoreExpirableInterface $keyValueExpirable;

  public function __construct(
    KeyValueExpirableFactoryInterface $key_value_expirable_factory,
    private readonly TimeInterface $time,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly UpdateStage $updateStage,
    private readonly ConsoleUpdateStage $consoleUpdateStage,
    private readonly CronUpdateRunner $cronUpdateRunner,
    private readonly int $resultsTimeToLive,
  ) {
    $this->keyValueExpirable = $key_value_expirable_factory->get('automatic_updates');
  }

  /**
   * Dispatches the status check event and stores the results.
   *
   * @return $this
   */
  public function run(): self {
    // If updates will run during cron, use the console update stage service
    // provided by this module. This will allow validators to run specific
    // validation for conditions that only affect cron updates.
    if ($this->cronUpdateRunner->getMode() === CronUpdateRunner::DISABLED) {
      $stage = $this->updateStage;
    }
    else {
      $stage = $this->consoleUpdateStage;
    }
    $results = $this->runStatusCheck($stage, $this->eventDispatcher);

    $this->keyValueExpirable->setWithExpire(
      'status_check_last_run',
      $results,
      $this->resultsTimeToLive * 60 * 60
    );
    $this->keyValueExpirable->set('status_check_timestamp', $this->time->getRequestTime());
    return $this;
  }

  /**
   * Dispatches the status check event if there no stored valid results.
   *
   * @return $this
   *
   * @see self::getResults()
   */
  public function runIfNoStoredResults(): self {
    if ($this->getResults() === NULL) {
      $this->run();
    }
    return $this;
  }

  /**
   * Gets the validation results from the last run.
   *
   * @param int|null $severity
   *   (optional) The severity for the results to return. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return \Drupal\package_manager\ValidationResult[]|null
   *   The validation result objects or NULL if no results are
   *   available or if the stored results are no longer valid.
   */
  public function getResults(?int $severity = NULL): ?array {
    $results = $this->keyValueExpirable->get('status_check_last_run');
    if ($results !== NULL) {
      if ($severity !== NULL) {
        $results = array_filter($results, function ($result) use ($severity) {
          return $result->severity === $severity;
        });
      }
      return $results;
    }
    return NULL;
  }

  /**
   * Deletes any stored status check results.
   */
  public function clearStoredResults(): void {
    $this->keyValueExpirable->delete('status_check_last_run');
  }

  /**
   * Gets the timestamp of the last run.
   *
   * @return int|null
   *   The timestamp of the last completed run, or NULL if no run has
   *   been completed.
   */
  public function getLastRunTime(): ?int {
    return $this->keyValueExpirable->get('status_check_timestamp');
  }

  /**
   * Reacts when config is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event object.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();

    // If the path of the Composer executable has changed, the status check
    // results are likely to change as well.
    if ($config->getName() === 'package_manager.settings' && $event->isChanged('executables.composer')) {
      $this->clearStoredResults();
    }
    elseif ($config->getName() === 'automatic_updates.settings') {
      // If anything about how we run unattended updates has changed, clear the
      // stored results, since they can be affected by these settings.
      if ($event->isChanged('unattended')) {
        $this->clearStoredResults();
      }
      // We only send status check failure notifications if unattended updates
      // are enabled. If notifications were previously disabled but have been
      // re-enabled, or their sensitivity level has changed, clear the stored
      // results so that we'll send accurate notifications next time cron runs.
      elseif ($event->isChanged('status_check_mail') && $config->get('status_check_mail') !== StatusCheckMailer::DISABLED) {
        $this->clearStoredResults();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostApplyEvent::class => 'clearStoredResults',
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

}
