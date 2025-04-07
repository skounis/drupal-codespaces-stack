<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Composer\Semver\Comparator;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule that forbids downgrading.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class ForbidDowngrade {

  use StringTranslationTrait;

  /**
   * Checks if the target version of Drupal is older than the installed version.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if not known.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version, ?string $target_version): array {
    // TRICKY: \Drupal\automatic_updates\Validator\VersionPolicyValidator::getTargetVersion() may potentially not be able to determine a version.
    $target_version = $target_version ?? '';
    if (Comparator::lessThan($target_version, $installed_version)) {
      return [
        $this->t('Update version @target_version is lower than @installed_version, downgrading is not supported.', [
          '@target_version' => $target_version,
          '@installed_version' => $installed_version,
        ]),
      ];
    }
    return [];
  }

}
