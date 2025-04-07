<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use DMore\ChromeDriver\ChromeDriver;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Database\StatementWrapper;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Checks for errors in logs and the browser console.
 */
class Log extends RawMinkContext {

  /**
   * Gets an array of log level labels.
   *
   * Only contains levels we care about in tests (we ignore debug or info level
   * logs).
   *
   * @return array<int, string>
   *   An array of log level labels.
   */
  protected static function getLogLevelLabelMap() : array {
    return [
      RfcLogLevel::NOTICE => 'Notice',
      RfcLogLevel::WARNING => 'Warning',
      RfcLogLevel::ERROR => 'Error',
      RfcLogLevel::CRITICAL => 'Critical Error',
      RfcLogLevel::ALERT => 'Alert',
      RfcLogLevel::EMERGENCY => 'Emergency',
    ];
  }

  /**
   * Ensure the dblog is enabled and store existing log entry count.
   *
   * @BeforeScenario
   */
  public function beforeScenario(BeforeScenarioScope $scope) : void {
    $environment = $scope->getEnvironment();
    assert($environment instanceof InitializedContextEnvironment);
    $setupContext = $environment->getContext(Setup::class);
    assert($setupContext instanceof Setup);
    $setupContext->assertModuleEnabled("dblog");

    $this->deleteAllLogMessages();
  }

  /**
   * Ensure there are no console messages after test steps.
   *
   * @AfterStep
   */
  public function assertCleanConsole() : void {
    $driver = $this->getSession()->getDriver();
    assert($driver instanceof ChromeDriver, "Not using ChromeDriver, console messages can't be tested.");

    // If we haven't opened any pages then there are no logs either.
    if (!$driver->isStarted()) {
      return;
    }

    $messages = $driver->getConsoleMessages();

    if (count($messages) !== 0) {
      throw new \RuntimeException("A step generated messages on the console: \n\n" . implode("\n", $messages));
    }
  }

  /**
   * Find the log entries since the start of the test and check for problems.
   *
   * We check this after every step so that people viewing the test output have
   * a clear indication of what step caused the problem.
   *
   * @AfterStep
   */
  public function assertCleanLogs(AfterStepScope $scope) : void {
    $messages = $this->getLogMessages();

    $error_labels = static::getLogLevelLabelMap();

    $problems = [];
    foreach ($messages as $dblog) {
      // Ignore debug information and only trigger on errors.
      if (!$dblog instanceof \StdClass || !isset($dblog->severity, $dblog->type, $error_labels[$dblog->severity])) {
        continue;
      }

      if ($this->isIgnoredLogMessage($dblog)) {
        continue;
      }

      $problems[] = $error_labels[$dblog->severity] . "(" . $dblog->type . "): " . $this->formatMessage($dblog);
    }

    $problem_count = count($problems);
    if ($problem_count !== 0) {
      throw new \Exception("The log showed $problem_count issues raised during this step.\n\n" . implode("\n--------------\n", $problems));
    }
  }

  /**
   * Drupal can produce a lot of log messages that are not actual problems.
   *
   * @param object $row
   *   The row from the watchdog table.
   *
   * @return bool
   *   Whether to ignore this message.
   */
  private function isIgnoredLogMessage(object $row) : bool {
    return (
      // Ignore notices from the user module since we don't really care about
      // users logging in or being deleted, those conditions are part of test
      // assertions.
      ($row->type === 'user' && (int) $row->severity === RfcLogLevel::NOTICE)
      // Ignore notices for the content type since we don't care about content
      // creation (and those should really be INFO anywhere, but that's a Drupal
      // core problem for another day).
      || ($row->type === 'content' && (int) $row->severity === RfcLogLevel::NOTICE)
      // Ignore comments being posted.
      || ($row->type === 'comment' && (int) $row->severity === RfcLogLevel::NOTICE)
      // Ignore language creation notices.
      || ($row->type === 'language' && (int) $row->severity === RfcLogLevel::NOTICE && str_contains($row->message, "language has been created"))
    );
  }

  /**
   * Format a message with variables provided.
   *
   * Modified from DbLogcontroller::formatMessage.
   *
   * @param object $row
   *   The watchdog database row.
   *
   * @return string|null
   *   A formatted string or NULL if message or variable were missing.
   */
  private function formatMessage(object $row) : ?string {
    if (!isset($row->message, $row->variables)) {
      return NULL;
    }

    $variables = @unserialize($row->variables, ['allowed_classes' => TRUE]);

    // Messages without variables or user specified text.
    if ($variables === NULL) {
      return Xss::filterAdmin($row->message);
    }

    if (!is_array($variables)) {
      return 'Log data is corrupted and cannot be unserialized: ' . Xss::filterAdmin($row->message);
    }

    // Format message with injected variables. We don't do translation in tests.
    return (string) (new TranslatableMarkup(
      // @phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      Xss::filterAdmin($row->message),
      $variables,
      [],
      // @phpstan-ignore-next-line
      \Drupal::service('string_translation')
    ));
  }

  /**
   * Clear out the watchdog table.
   */
  private function deleteAllLogMessages() : void {
    // @phpstan-ignore-next-line
    \Drupal::database()->truncate('watchdog')->execute();
  }

  /**
   * Get the messages stored in the watchdog table.
   *
   * We must query for this manually taking inspiration from the DbLogController
   * because there's no service that provides proper non-database access.
   *
   * @return \Drupal\Core\Database\StatementWrapper
   *   The result of the log message query.
   */
  private function getLogMessages() : StatementWrapper {
    // @phpstan-ignore-next-line
    $query = \Drupal::database()->select('watchdog', 'w')
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    assert($query instanceof SelectInterface);

    $query->fields('w', [
      'wid',
      'uid',
      'severity',
      'type',
      'timestamp',
      'message',
      'variables',
      'link',
    ]);
    $query->leftJoin('users_field_data', 'ufd', '[w].[uid] = [ufd].[uid]');

    $result = $query->execute();
    assert($result instanceof StatementWrapper, "Invalid query for watchdog table");
    return $result;
  }

}
