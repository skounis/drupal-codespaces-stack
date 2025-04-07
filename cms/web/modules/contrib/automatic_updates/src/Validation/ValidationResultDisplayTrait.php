<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\SystemManager;

/**
 * Common methods for displaying validation results in the admin UI.
 *
 * @internal
 *   This trait implements logic to output the messages from readiness checkers
 *   on admin pages. It may be changed or removed at any time without warning
 *   and should not be used by external code.
 */
trait ValidationResultDisplayTrait {

  /**
   * Gets a message, based on severity, when status checks fail.
   *
   * @param int $severity
   *   The severity. Should be one of the SystemManager::REQUIREMENT_*
   *   constants.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   *
   * @see \Drupal\system\SystemManager::REQUIREMENT_ERROR
   * @see \Drupal\system\SystemManager::REQUIREMENT_WARNING
   */
  private function getFailureMessageForSeverity(int $severity): TranslatableMarkup {
    return $severity === SystemManager::REQUIREMENT_WARNING ?
      // @todo Link "automatic updates" to documentation in
      //   https://www.drupal.org/node/3168405.
      $this->t('Your site does not pass some readiness checks for automatic updates. Depending on the nature of the failures, it might affect the eligibility for automatic updates.') :
      $this->t('Your site does not pass some readiness checks for automatic updates. It cannot be automatically updated until further action is performed.');
  }

}
