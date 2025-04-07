<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Composer\Semver\VersionParser;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule requiring the target version to be a RC or higher.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class TargetVersionNotPreRelease {

  use StringTranslationTrait;

  /**
   * Checks that the target version of Drupal is a stable release.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal, or NULL if not known.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version, ?string $target_version): array {
    if (!in_array(VersionParser::parseStability($target_version), ['stable', 'RC'], TRUE)) {
      return [
        $this->t('Drupal cannot be updated to the recommended version, @target_version, because it is not a stable version.', [
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}
