<?php

namespace Drupal\automatic_updates;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\package_manager\PathLocator;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Creates and starts `auto-update` terminal command processes.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class CommandExecutor {

  public function __construct(
    private readonly PathLocator $pathLocator,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
    private readonly string $appRoot,
  ) {}

  /**
   * Creates a process to invoke the `auto-update` terminal command.
   *
   * @param string|null $arguments
   *   (optional) Additional arguments and/or options to append to the
   *   command line.
   *
   * @return \Symfony\Component\Process\Process
   *   A process to invoke the `auto-update` terminal command in a consistent
   *   way, with the `--host` and `--site-path` options always set.
   */
  public function create(?string $arguments = NULL): Process {
    $script = $this->appRoot . '/core/scripts/auto-update';
    // BEGIN: DELETE FROM CORE MERGE REQUEST
    $script = __DIR__ . '/../auto-update';
    // END: DELETE FROM CORE MERGE REQUEST
    $command_line = implode(' ', [
      // Always run the command script directly through the PHP interpreter.
      (new PhpExecutableFinder())->find(),
      // Always pass the fully resolved script path to the interpreter.
      $this->fileSystem->realpath($script),
      // Always explicitly specify the base URI, which will allow this to work
      // consistently in functional tests.
      '--uri=' . Url::fromRoute('<front>')->setAbsolute()->toString(),
    ]);
    if ($arguments) {
      $command_line .= " $arguments";
    }

    return Process::fromShellCommandline($command_line, $this->pathLocator->getProjectRoot())
      ->setTimeout(0);
  }

  /**
   * Starts a process and waits for it to have a process ID.
   *
   * This is meant to be used when starting a detached process, otherwise
   * the current web request may end before the process has a chance to
   * start.
   *
   * @param \Symfony\Component\Process\Process $process
   *   The process to start.
   * @param int $timeout
   *   How long to wait for a process ID before giving up, in seconds.
   *
   * @return int|null
   *   The running process ID, or NULL if it didn't start after $timeout
   *   seconds.
   */
  public function start(Process $process, int $timeout = 5): ?int {
    $process->start();

    $wait_until = $this->time->getCurrentTime() + $timeout;
    do {
      sleep(1);
      $pid = $process->getPid();
      if ($pid) {
        return $pid;
      }
    } while ($wait_until > $this->time->getCurrentTime());

    return NULL;
  }

}
