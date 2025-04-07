<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\user\Entity\User;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Parent class for ECA model tests.
 */
abstract class Base extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'eca',
  ];

  /**
   * The service name for a logger implementation that collects anything logged.
   *
   * @var string
   */
  protected static string $testLogServiceName = 'eca_test.logger';

  protected const USER_1_NAME = 'eca-test';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp(): void {
    Eca::setTesting();
    parent::setUp();

    // Install config for modules of this base class.
    $this->installConfig(['user', 'system', 'field', 'text', 'eca']);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    // Install config for modules of the implementing test class.
    $this->installConfig(static::$modules);

    // Create user 1.
    User::create([
      'uid' => 1,
      'name' => self::USER_1_NAME,
      'mail' => 'eca@localhost',
      'status' => TRUE,
    ])->save();

    // Prepare the logger for collecting ECA log messages.
    $this->container->get(self::$testLogServiceName)->cleanLogs();
    // Enable all log levels by default.
    $this->setLogLevel(RfcLogLevel::ERROR);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register(self::$testLogServiceName, BufferingLogger::class)
      ->addTag('logger');
  }

  /**
   * Switch to the given user.
   *
   * @param int $id
   *   The ID of the user to which to switch.
   */
  protected function switchUser(int $id = 1): void {
    $user = User::load($id);
    \Drupal::service('account_switcher')->switchTo($user);
  }

  /**
   * Configures the ECA log level.
   *
   * @param int $level
   *   The RfcLogLevel:: level which should be configured for ECA.
   */
  protected function setLogLevel(int $level): void {
    \Drupal::service('logger.channel.eca')->updateLogLevel($level);
  }

  /**
   * Disable a given ECA model.
   *
   * @param string $id
   *   The ID of the model to be disabled.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableEcaModel(string $id): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    if ($eca = \Drupal::entityTypeManager()->getStorage('eca')->load($id)) {
      $eca->disable()->save();
    }
  }

  /**
   * Verify that no error or worse has been logged.
   *
   * Optionally this can also assert a number of expected log records, that
   * need to be present and won't be treated as available errors.
   *
   * @param \Drupal\Tests\eca\Kernel\Model\LogRecord[] $logRecords
   *   List of expected log records.
   *
   * @throws \Exception
   */
  protected function assertNoError(array $logRecords = []): void {
    foreach ($this->container->get(self::$testLogServiceName)->cleanLogs() as $log_message) {
      foreach ($logRecords as $index => $logRecord) {
        if ($logRecord->compare($log_message[0], $log_message[2]['channel'], $log_message[1], $log_message[2])) {
          $this->assertNotNull('record exists', $logRecord->__toString());
          unset($logRecords[$index]);
          continue 2;
        }
      }
      $this->assertGreaterThan(RfcLogLevel::ERROR, $log_message[0], strip_tags((string) (new FormattableMarkup($log_message[1], $log_message[2]))));
    }
    self::assertEmpty($logRecords, 'Expected log records missing: ' . PHP_EOL . implode(PHP_EOL, $logRecords));
  }

  /**
   * Verify that all expected messages are available.
   *
   * @param string[] $expected
   *   List of expected messages.
   * @param string[]|\Drupal\Component\Render\MarkupInterface[] $messages
   *   List of actual messages.
   */
  protected function assertMessages(array $expected, array $messages): void {
    foreach ($messages as $message) {
      $key = array_search((string) $message, $expected, TRUE);
      self::assertNotFalse($key, "Message '$message' is unexpected.");
      if ($key !== FALSE) {
        unset($expected[$key]);
      }
    }
    self::assertEmpty($expected, 'Expected messages missing: ' . PHP_EOL . implode(PHP_EOL, $expected));
  }

  /**
   * Verify that all expected status messages are available.
   *
   * @param string[] $expected
   *   List of expected messages.
   */
  protected function assertStatusMessages(array $expected): void {
    $this->assertMessages($expected, \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS));
  }

  /**
   * Verify that all expected warning messages are available.
   *
   * @param string[] $expected
   *   List of expected messages.
   */
  protected function assertWarningMessages(array $expected): void {
    $this->assertMessages($expected, \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_WARNING));
  }

  /**
   * Verify that all expected error messages are available.
   *
   * @param string[] $expected
   *   List of expected messages.
   */
  protected function assertErrorMessages(array $expected): void {
    $this->assertMessages($expected, \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * Verify that no messages are available.
   */
  protected function assertNoMessages(): void {
    $this->assertMessages([], \Drupal::messenger()->deleteAll());
  }

}

/**
 * Helper class to compare log records.
 */
class LogRecord {

  /**
   * Log severity.
   *
   * @var int
   */
  private int $severity;

  /**
   * Log channel.
   *
   * @var string
   */
  private string $channel;

  /**
   * Log message.
   *
   * @var string
   */
  private string $message;

  /**
   * Log arguments/context.
   *
   * @var array
   */
  private array $arguments;

  /**
   * Constructs a log record.
   *
   * @param int $severity
   *   The log severity.
   * @param string $channel
   *   The log channel.
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The list of log message arguments.
   */
  public function __construct(int $severity, string $channel, string $message, array $arguments = []) {
    $this->severity = $severity;
    $this->channel = $channel;
    $this->message = $message;
    $this->arguments = $arguments;
  }

  /**
   * Formats and cleans the log record.
   *
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The log message arguments.
   *
   * @return string
   *   The formatted message string.
   */
  public static function format(string $message, array $arguments = []): string {
    return strip_tags(strtr($message, $arguments));
  }

  /**
   * Compares the current log record to the given values.
   *
   * @param int $severity
   *   The log severity.
   * @param string $channel
   *   The log channel.
   * @param string $message
   *   The log message.
   * @param array $arguments
   *   The list of log message arguments.
   *
   * @return bool
   *   Returns TRUE, if all components equal the current log record, FALSE
   *   otherwise.
   */
  public function compare(int $severity, string $channel, string $message, array $arguments = []): bool {
    return $this->severity === $severity &&
      $this->channel === $channel &&
      $this->__toString() === self::format($message, $arguments);
  }

  /**
   * Return the formatted version of the current log record.
   *
   * @return string
   *   The formatted message string.
   */
  public function __toString(): string {
    return self::format($this->message, $this->arguments);
  }

}
