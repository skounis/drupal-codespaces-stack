<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\Core\Extension\ExtensionVersion;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A policy rule that forbids updating from a dev snapshot.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class ForbidDevSnapshot {

  use StringTranslationTrait;

  /**
   * Checks if the installed version of Drupal is a dev snapshot.
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

    if ($extra === 'dev') {
      return [
        $this->t('Drupal cannot be automatically updated from the installed version, @installed_version, because automatic updates from a dev version to any other version are not supported.', [
          '@installed_version' => $installed_version,
        ]),
      ];
    }
    return [];
  }

}
