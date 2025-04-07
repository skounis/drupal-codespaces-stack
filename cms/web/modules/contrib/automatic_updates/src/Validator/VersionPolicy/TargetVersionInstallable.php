<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator\VersionPolicy;

use Drupal\automatic_updates\VersionParsingTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A policy rule requiring the target version to be installable.
 *
 * @internal
 *   This is an internal part of Automatic Updates' version policy for
 *   Drupal core. It may be changed or removed at any time without warning.
 *   External code should not interact with this class.
 */
final class TargetVersionInstallable implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use VersionParsingTrait;

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Checks that the target version can be installed.
   *
   * This means two things must be true:
   * - The target minor version of Drupal can be updated to. The update will
   *   only be allowed if the allow_core_minor_updates flag is TRUE in config.
   * - The target version of Drupal is a known installable release.
   *
   * If the first check fails, there is no need to do the second check because
   * the first check implies that the target version isn't installable.
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
    $installed_minor = static::getMajorAndMinorVersion($installed_version);
    $target_minor = static::getMajorAndMinorVersion($target_version);

    if ($installed_minor !== $target_minor) {
      $minor_updates_allowed = $this->configFactory->get('automatic_updates.settings')
        ->get('allow_core_minor_updates');

      if (!$minor_updates_allowed) {
        return [
          $this->t('Drupal cannot be automatically updated from @installed_version to @target_version because automatic updates from one minor version to another are not supported.', [
            '@installed_version' => $installed_version,
            '@target_version' => $target_version,
          ]),
        ];
      }
    }
    // If the target version isn't in the list of installable releases, we
    // should flag an error.
    if (empty($available_releases) || !array_key_exists($target_version, $available_releases)) {
      return [
        $this->t('Cannot update Drupal core to @target_version because it is not in the list of installable releases.', [
          '@target_version' => $target_version,
        ]),
      ];
    }
    return [];
  }

}
