<?php

namespace Drupal\eca;

/**
 * Provides helper functions to enable and reset extended error handling.
 */
trait ErrorHandlerTrait {

  /**
   * The error level before enabling extended mode.
   *
   * @var int
   */
  protected int $originalErrorHandling = 0;

  /**
   * Determines if the shutdown function is enabled or disabled.
   *
   * @var bool
   */
  protected bool $shutdownFunctionEnabled = FALSE;

  /**
   * Helper function to enable extended error handling.
   */
  protected function enableExtendedErrorHandling(string $context): void {
    $buildMessage = static function (string $message, string $file, int $line) use ($context): string {
      return 'ECA ran into error from third party in the context of "' . $context . '": ' . PHP_EOL . $message . PHP_EOL . 'Line ' . $line . ' of ' . $file;
    };

    // Set the error handler.
    set_error_handler(function (int $level, string $message, string $file, int $line) use ($buildMessage): bool {
      if ($level === E_USER_DEPRECATED || $level === E_DEPRECATED) {
        return FALSE;
      }
      $this->logger->error($buildMessage($message, $file, $line));
      return FALSE;
    });

    // Handle fatal errors.
    $this->shutdownFunctionEnabled = TRUE;
    $shutdownFunctionEnabled = &$this->shutdownFunctionEnabled;
    register_shutdown_function(function () use ($buildMessage, &$shutdownFunctionEnabled) {
      // @phpstan-ignore-next-line
      if (!$shutdownFunctionEnabled) {
        return;
      }
      $error = error_get_last();
      if ($error === NULL) {
        return;
      }
      echo $buildMessage($error['message'], $error['file'], $error['line']);
    });

    // Turn off reporting.
    $this->originalErrorHandling = error_reporting(0);
  }

  /**
   * Helper function to reset extended error handling.
   */
  protected function resetExtendedErrorHandling(): void {
    $this->shutdownFunctionEnabled = FALSE;
    error_reporting($this->originalErrorHandling);
    restore_error_handler();
  }

}
