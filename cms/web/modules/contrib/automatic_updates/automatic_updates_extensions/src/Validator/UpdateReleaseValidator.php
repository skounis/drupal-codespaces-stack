<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions\Validator;

use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that updated projects are secure and supported.
 *
 * @internal
 *   This class is an internal part of the module's update handling and
 *   should not be used by external code.
 *
 * @todo Decide if this validator can be removed completely in
 *    https://www.drupal.org/i/3351091.
 */
final class UpdateReleaseValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ComposerInspector $composerInspector,
    private readonly PathLocator $pathLocator,
  ) {}

  /**
   * Checks if the given version of a project is supported.
   *
   * Checks if the given version of the given project is in the core update
   * system's list of known, secure, installable releases of that project.
   * considered a supported release by verifying if the project is found in the
   * core update system's list of known, secure, and installable releases.
   *
   * @param string $name
   *   The name of the project.
   * @param string $semantic_version
   *   A semantic version number for the project.
   *
   * @return bool
   *   TRUE if the given version of the project is supported, otherwise FALSE.
   *   given version is not supported will return FALSE.
   */
  private function isSupportedRelease(string $name, string $semantic_version): bool {
    $supported_releases = (new ProjectInfo($name))->getInstallableReleases();
    if (!$supported_releases) {
      return FALSE;
    }

    // If this version is found in the list of installable releases, it is
    // secured and supported.
    if (array_key_exists($semantic_version, $supported_releases)) {
      return TRUE;
    }
    // If the semantic version number wasn't in the list of
    // installable releases, convert it to a legacy version number and see
    // if the version number is in the list.
    $legacy_version = LegacyVersionUtility::convertToLegacyVersion($semantic_version);
    if ($legacy_version && array_key_exists($legacy_version, $supported_releases)) {
      return TRUE;
    }
    // Neither the semantic version nor the legacy version are in the list
    // of installable releases, so the release isn't supported.
    return FALSE;
  }

  /**
   * Checks that the update projects are secure and supported.
   *
   * @param \Drupal\package_manager\Event\PreCreateEvent $event
   *   The event object.
   */
  public function checkRelease(PreCreateEvent $event): void {
    $stage = $event->stage;
    // This check only works with Automatic Updates Extensions.
    if ($stage->getType() !== 'automatic_updates_extensions:attended') {
      return;
    }

    $active_packages = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $all_versions = $stage->getPackageVersions();
    $messages = [];
    foreach (['production', 'dev'] as $package_type) {
      foreach ($all_versions[$package_type] as $package_name => $sematic_version) {
        $project_name = $active_packages[$package_name]->getProjectName();
        // If the version isn't in the list of installable releases, then it
        // isn't secure and supported and the user should receive an error.
        if (!$this->isSupportedRelease($project_name, $sematic_version)) {
          $messages[] = $this->t('Project @project_name to version @version', [
            '@project_name' => $project_name,
            '@version' => $sematic_version,
          ]);
        }
      }
    }
    if ($messages) {
      $summary = $this->formatPlural(
        count($messages),
        'Cannot update because the following project version is not in the list of installable releases.',
        'Cannot update because the following project versions are not in the list of installable releases.'
      );
      $event->addError($messages, $summary);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'checkRelease',
    ];
  }

}
