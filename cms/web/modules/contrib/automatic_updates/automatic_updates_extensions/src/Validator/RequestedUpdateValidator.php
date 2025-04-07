<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions\Validator;

use Composer\Semver\Semver;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that requested packages have been updated.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class RequestedUpdateValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ComposerInspector $composerInspector,
    private readonly PathLocator $pathLocator,
  ) {}

  /**
   * Validates that requested packages have been updated to the right version.
   *
   * @param \Drupal\package_manager\Event\PreApplyEvent|\Drupal\package_manager\Event\StatusCheckEvent $event
   *   The pre-apply event.
   */
  public function checkRequestedStagedVersion(PreApplyEvent|StatusCheckEvent $event): void {
    $stage = $event->stage;
    if ($stage->getType() !== 'automatic_updates_extensions:attended' || !$stage->stageDirectoryExists()) {
      return;
    }
    $requested_package_versions = $stage->getPackageVersions();
    $active = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $staged = $this->composerInspector->getInstalledPackagesList($event->stage->getStageDirectory());
    $changed_stage_packages = $staged->getPackagesWithDifferentVersionsIn($active)->getArrayCopy();

    if (empty($changed_stage_packages)) {
      $event->addError([$this->t('No updates detected in the staging area.')]);
      return;
    }

    // Check for all changed the packages if they are updated to the requested
    // version.
    foreach (['production', 'dev'] as $package_type) {
      foreach ($requested_package_versions[$package_type] as $requested_package_name => $requested_version) {
        if (array_key_exists($requested_package_name, $changed_stage_packages)) {
          $staged_version = $changed_stage_packages[$requested_package_name]->version;
          if (!Semver::satisfies($staged_version, $requested_version)) {
            $event->addError([
              $this->t(
                "The requested update to '@requested_package_name' to version '@requested_version' does not match the actual staged update to '@staged_version'.",
                [
                  '@requested_package_name' => $requested_package_name,
                  '@requested_version' => $requested_version,
                  '@staged_version' => $staged_version,
                ]
              ),
            ]);
          }
        }
        else {
          $event->addError([
            $this->t(
              "The requested update to '@requested_package_name' to version '@requested_version' was not performed.",
              [
                '@requested_package_name' => $requested_package_name,
                '@requested_version' => $requested_version,
              ]
            ),
          ]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[StatusCheckEvent::class][] = ['checkRequestedStagedVersion'];
    $events[PreApplyEvent::class][] = ['checkRequestedStagedVersion'];
    return $events;
  }

}
