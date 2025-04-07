<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\Core\Extension\ExtensionVersion;

/**
 * Common function for parsing version traits.
 *
 * @internal
 *   This trait may be removed in patch or minor versions.
 */
trait VersionParsingTrait {

  /**
   * Gets the patch number from a version string.
   *
   * @todo Move this method to \Drupal\Core\Extension\ExtensionVersion in
   *   https://www.drupal.org/i/3261744.
   *
   * @param string $version_string
   *   The version string.
   *
   * @return string
   *   The patch number. If not known, defaults to '0'.
   */
  private static function getPatchVersion(string $version_string): string {
    $version_extra = ExtensionVersion::createFromVersionString($version_string)
      ->getVersionExtra();
    if ($version_extra) {
      $version_string = str_replace("-$version_extra", '', $version_string);
    }
    return explode('.', $version_string)[2] ?? '0';
  }

  /**
   * Returns the semantic major.minor numbers of a version string.
   *
   * @param string $version
   *   The version string.
   *
   * @return string
   *   The major.minor numbers of the version string. For example, if $version
   *   is 8.9.1, '8.9' will be returned.
   */
  private static function getMajorAndMinorVersion(string $version): string {
    $version = ExtensionVersion::createFromVersionString($version);
    return $version->getMajorVersion() . '.' . $version->getMinorVersion();
  }

}
