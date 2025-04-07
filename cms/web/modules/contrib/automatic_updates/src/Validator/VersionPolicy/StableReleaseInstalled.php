<?php

declare(strict_types=1);


namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\Core\Extension\ExtensionVersion;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule that requiring the installed version to be stable.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class StableReleaseInstalled {

  use StringTranslationTrait;

  /**
   * Checks if the installed version of Drupal is a stable release.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version): array {
    $extra = ExtensionVersion::createFromVersionString($installed_version)
      ->getVersionExtra();

    if ($extra) {
      return [
        $this->t('Drupal cannot be automatically updated during cron from its current version, @installed_version, because it is not a stable version.', [
          '@installed_version' => $installed_version,
        ]),
      ];
    }
    return [];
  }

}
