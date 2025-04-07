<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Utility\Error;
use Drupal\package_manager\PathLocator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Runs updates as a detached background process after regular cron tasks.
 *
 * The update process will be started in a detached process which will continue
 * running after the web request has terminated. This is done after the
 * decorated cron service has been called, so regular cron tasks will always be
 * run regardless of whether there is an update available and whether an update
 * is successful.
 *
 * @internal
 *   This class implements logic specific to Automatic Updates' cron hook
 *   implementation and may be changed or removed at any time without warning.
 *   It should not be called directly, and external code should not interact
 *   with it.
 */
class CronUpdateRunner implements CronInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The current interface between PHP and the server.
   *
   * @var string
   */
  private static $serverApi = PHP_SAPI;

  /**
   * All automatic updates are disabled.
   *
   * @var string
   */
  public const DISABLED = 'disable';

  /**
   * Only perform automatic security updates.
   *
   * @var string
   */
  public const SECURITY = 'security';

  /**
   * All automatic updates are enabled.
   *
   * @var string
   */
  public const ALL = 'patch';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly PathLocator $pathLocator,
    private readonly CronInterface $inner,
    private readonly CommandExecutor $commandExecutor,
  ) {}

  /**
   * Runs the terminal update command.
   */
  protected function runTerminalUpdateCommand(): void {
    // Use the `&` on the command line to detach this process after it is
    // started. This will allow the command to outlive the web request.
    $process = $this->commandExecutor->create('--is-from-web &');

    try {
      $pid = $this->commandExecutor->start($process);
    }
    catch (\Throwable $throwable) {
      if ($this->logger) {
        Error::logException($this->logger, $throwable, 'Unable to start background update.');
      }
    }

    if ($process->isTerminated()) {
      if ($process->getExitCode() !== 0) {
        $this->logger?->error('Background update failed: %message', [
          '%message' => $process->getErrorOutput(),
        ]);
      }
    }
    elseif (empty($pid)) {
      $this->logger?->error('Background update failed because the process did not start within 5 seconds.');
    }
  }

  /**
   * Indicates if we are currently running at the command line.
   *
   * @return bool
   *   TRUE if we are running at the command line, otherwise FALSE.
   */
  final public static function isCommandLine(): bool {
    return self::$serverApi === 'cli';
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    // Always run the cron service before we trigger the update terminal
    // command.
    $decorated_cron_succeeded = $this->inner->run();

    $method = $this->configFactory->get('automatic_updates.settings')
      ->get('unattended.method');
    // If we are configured to run updates via the web, and we're actually being
    // accessed via the web (i.e., anything that isn't the command line), go
    // ahead and try to do the update.
    if ($method === 'web' && !self::isCommandLine()) {
      $this->runTerminalUpdateCommand();
    }
    return $decorated_cron_succeeded;
  }

  /**
   * Gets the cron update mode.
   *
   * @return string
   *   The cron update mode. Will be one of the following constants:
   *   - self::DISABLED if updates during
   *     cron are entirely disabled.
   *   - self::SECURITY only security
   *     updates can be done during cron.
   *   - self::ALL if all updates are
   *     allowed during cron.
   */
  final public function getMode(): string {
    $mode = $this->configFactory->get('automatic_updates.settings')->get('unattended.level');
    return $mode ?: static::SECURITY;
  }

}
