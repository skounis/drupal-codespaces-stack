<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule requiring the target version to be a security release.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class TargetSecurityRelease {

  use StringTranslationTrait;

  /**
   * Checks that the target version of Drupal is a security release.
   *
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal, or NULL if not known.
   * @param \Drupal\update\ProjectRelease[] $available_releases
   *   The available releases of Drupal core.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages, if any.
   */
  public function validate(string $installed_version, ?string $target_version, array $available_releases): array {
    if (!$available_releases[$target_version]->isSecurityRelease()) {
      return [
        $this->t('Drupal cannot be automatically updated during cron from @installed_version to @target_version because @target_version is not a security release.', [
          '@installed_version' => $installed_version,
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}
