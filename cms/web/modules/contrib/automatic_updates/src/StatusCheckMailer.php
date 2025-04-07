<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;

/**
 * Defines a service to send status check failure emails during cron.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class StatusCheckMailer {

  /**
   * Never send failure notifications.
   *
   * @var string
   */
  public const DISABLED = 'disabled';

  /**
   * Send failure notifications if status checks raise any errors or warnings.
   *
   * @var string
   */
  public const ALL = 'all';

  /**
   * Only send failure notifications if status checks raise errors.
   *
   * @var string
   */
  public const ERRORS_ONLY = 'errors_only';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Sends status check failure notifications if necessary.
   *
   * Notifications will only be sent if the following conditions are fulfilled:
   * - Notifications are enabled.
   * - If we are configured to only send notifications if there are errors, the
   *   current result set must contain at least one error result.
   * - The previous and current result sets, after filtering, are different.
   *
   * @param \Drupal\package_manager\ValidationResult[]|null $previous_results
   *   The previous set of status check results, if any.
   * @param \Drupal\package_manager\ValidationResult[] $current_results
   *   The current set of status check results.
   */
  public function sendFailureNotifications(?array $previous_results, array $current_results): void {
    $level = $this->configFactory->get('automatic_updates.settings')
      ->get('status_check_mail');

    if ($level === static::DISABLED) {
      return;
    }
    // If we're ignoring warnings, filter them out of the previous and current
    // result sets.
    elseif ($level === static::ERRORS_ONLY) {
      $filter = function (ValidationResult $result): bool {
        return $result->severity === SystemManager::REQUIREMENT_ERROR;
      };
      $current_results = array_filter($current_results, $filter);
      // If the current results don't have any errors, there's nothing else
      // for us to do.
      if (empty($current_results)) {
        return;
      }

      if ($previous_results) {
        $previous_results = array_filter($previous_results, $filter);
      }
    }

    if ($this->resultsAreDifferent($previous_results, $current_results)) {
      foreach ($this->getRecipients() as $email => $langcode) {
        $this->mailManager->mail('automatic_updates', 'status_check_failed', $email, $langcode, []);
      }
    }
  }

  /**
   * Determines if two sets of validation results are different.
   *
   * @param \Drupal\package_manager\ValidationResult[]|null $previous_results
   *   The previous set of validation results, if any.
   * @param \Drupal\package_manager\ValidationResult[] $current_results
   *   The current set of validation results.
   *
   * @return bool
   *   TRUE if the given result sets are different; FALSE otherwise.
   */
  private function resultsAreDifferent(?array $previous_results, array $current_results): bool {
    if ($previous_results === NULL || count($previous_results) !== count($current_results)) {
      return TRUE;
    }

    // We can't rely on the previous and current result sets being in the same
    // order, so we need to use this inefficient nested loop to check if each
    // previous result is anywhere in the current result set. This is a case
    // where accuracy is probably more important than performance.
    $result_previously_existed = function (ValidationResult $result) use ($previous_results): bool {
      foreach ($previous_results as $previous_result) {
        if (ValidationResult::isEqual($result, $previous_result)) {
          return TRUE;
        }
      }
      return FALSE;
    };
    foreach ($current_results as $result) {
      if (!$result_previously_existed($result)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns an array of people to email.
   *
   * @return string[]
   *   An array whose keys are the email addresses to send notifications to, and
   *   values are the langcodes that they should be emailed in.
   */
  public function getRecipients(): array {
    $recipients = $this->configFactory->get('update.settings')
      ->get('notification.emails');
    $emails = [];
    foreach ($recipients as $recipient) {
      $emails[$recipient] = $this->getEmailLangcode($recipient);
    }
    return $emails;
  }

  /**
   * Retrieves preferred language to send email.
   *
   * @param string $recipient
   *   The email address of the recipient.
   *
   * @return string
   *   The preferred language of the recipient.
   */
  private function getEmailLangcode(string $recipient): string {
    $user = user_load_by_mail($recipient);
    if ($user) {
      return $user->getPreferredLangcode();
    }
    return $this->languageManager->getDefaultLanguage()->getId();
  }

}
