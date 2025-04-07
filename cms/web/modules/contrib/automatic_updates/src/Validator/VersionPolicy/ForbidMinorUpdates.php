<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\automatic_updates\VersionParsingTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule forbidding minor updates during cron.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class ForbidMinorUpdates {

  use StringTranslationTrait;
  use VersionParsingTrait;

  /**
   * Checks if the target minor version of Drupal is different than installed.
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
    $installed_minor = static::getMajorAndMinorVersion($installed_version);
    $target_minor = static::getMajorAndMinorVersion($target_version);

    if ($installed_minor !== $target_minor) {
      return [
        $this->t('Drupal cannot be automatically updated from @installed_version to @target_version because automatic updates from one minor version to another are not supported during cron.', [
          '@installed_version' => $installed_version,
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}
