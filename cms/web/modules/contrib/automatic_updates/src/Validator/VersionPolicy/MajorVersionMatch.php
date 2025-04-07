<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\Core\Extension\ExtensionVersion;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule that requires updating within the same major version.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class MajorVersionMatch {

  use StringTranslationTrait;

  /**
   * Checks that the target version of Drupal is in the same minor as installed.
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
    $installed_major = ExtensionVersion::createFromVersionString($installed_version)
      ->getMajorVersion();
    $target_major = ExtensionVersion::createFromVersionString($target_version)
      ->getMajorVersion();

    if ($installed_major !== $target_major) {
      return [
        $this->t('Drupal cannot be automatically updated from @installed_version to @target_version because automatic updates from one major version to another are not supported.', [
          '@installed_version' => $installed_version,
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}
