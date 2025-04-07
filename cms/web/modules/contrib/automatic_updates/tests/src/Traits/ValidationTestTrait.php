<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Traits;

use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;
use Drupal\Tests\package_manager\Traits\ValidationTestTrait as PackageManagerValidationTestTrait;

/**
 * Common methods for testing validation.
 *
 * @internal
 */
trait ValidationTestTrait {

  use PackageManagerValidationTestTrait;

  /**
   * Expected explanation text when status checkers return error messages.
   *
   * @var string
   */
  protected static $errorsExplanation = 'Your site does not pass some readiness checks for automatic updates. It cannot be automatically updated until further action is performed.';

  /**
   * Expected explanation text when status checkers return warning messages.
   *
   * @var string
   */
  protected static $warningsExplanation = 'Your site does not pass some readiness checks for automatic updates. Depending on the nature of the failures, it might affect the eligibility for automatic updates.';

  /**
   * Creates a unique validation test result.
   *
   * @param int $severity
   *   The severity. Should be one of the SystemManager::REQUIREMENT_*
   *   constants.
   * @param int $message_count
   *   (optional) The number of messages. Defaults to 1.
   *
   * @return \Drupal\package_manager\ValidationResult
   *   The validation test result.
   */
  protected function createValidationResult(int $severity, int $message_count = 1): ValidationResult {
    $this->assertNotEmpty($message_count);
    $messages = [];
    $random = $this->randomMachineName(64);
    for ($i = 0; $i < $message_count; $i++) {
      $messages[] = t("Message @i @random", ['@i' => $i, '@random' => $random]);
    }
    $summary = t('Summary @random', ['@random' => $random]);
    switch ($severity) {
      case SystemManager::REQUIREMENT_ERROR:
        return ValidationResult::createError($messages, $summary);

      case SystemManager::REQUIREMENT_WARNING:
        return ValidationResult::createWarning($messages, $summary);

      default:
        throw new \InvalidArgumentException("$severity is an invalid value for \$severity; it must be SystemManager::REQUIREMENT_ERROR or SystemManager::REQUIREMENT_WARNING.");
    }
  }

  /**
   * Gets the messages of a particular type from the manager.
   *
   * @param bool $call_run
   *   Whether to run the checkers.
   * @param int|null $severity
   *   (optional) The severity for the results to return. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return \Drupal\package_manager\ValidationResult[]|null
   *   The messages of the type.
   */
  protected function getResultsFromManager(bool $call_run = FALSE, ?int $severity = NULL): ?array {
    $manager = $this->container->get(StatusChecker::class);
    if ($call_run) {
      $manager->run();
    }
    return $manager->getResults($severity);
  }

  /**
   * Asserts expected validation results from the manager.
   *
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected results.
   * @param bool $call_run
   *   (Optional) Whether to call ::run() on the manager. Defaults to FALSE.
   * @param int|null $severity
   *   (optional) The severity for the results to return. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   */
  protected function assertCheckerResultsFromManager(array $expected_results, bool $call_run = FALSE, ?int $severity = NULL): void {
    $actual_results = $this->getResultsFromManager($call_run, $severity);
    $this->assertValidationResultsEqual($expected_results, $actual_results);
  }

}
