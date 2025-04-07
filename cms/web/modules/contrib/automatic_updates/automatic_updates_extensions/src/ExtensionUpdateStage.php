<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\FailureMarker;
use Drupal\package_manager\LegacyVersionUtility;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\StageBase;
use PhpTuf\ComposerStager\API\Core\BeginnerInterface;
use PhpTuf\ComposerStager\API\Core\CommitterInterface;
use PhpTuf\ComposerStager\API\Core\StagerInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service to perform updates for modules and themes.
 *
 * @internal
 *   This class is an internal part of the module's update handling and
 *   should not be used by external code.
 */
final class ExtensionUpdateStage extends StageBase {

  /**
   * {@inheritdoc}
   */
  protected string $type = 'automatic_updates_extensions:attended';

  public function __construct(
    protected readonly ComposerInspector $composerInspector,
    PathLocator $pathLocator,
    BeginnerInterface $beginner,
    StagerInterface $stager,
    CommitterInterface $committer,
    QueueFactory $queueFactory,
    EventDispatcherInterface $eventDispatcher,
    SharedTempStoreFactory $tempStoreFactory,
    TimeInterface $time,
    PathFactoryInterface $pathFactory,
    FailureMarker $failureMarker,
  ) {
    parent::__construct($pathLocator, $beginner, $stager, $committer, $queueFactory, $eventDispatcher, $tempStoreFactory, $time, $pathFactory, $failureMarker);
  }

  /**
   * Begins the update.
   *
   * @param string[] $project_versions
   *   The versions of the packages to update to, keyed by package name.
   *
   * @return string
   *   The unique ID of the stage.
   *
   * @throws \InvalidArgumentException
   *   Thrown if no project version is provided.
   */
  public function begin(array $project_versions): string {
    if (empty($project_versions)) {
      throw new \InvalidArgumentException("No projects to begin the update");
    }
    $package_versions = [
      'production' => [],
      'dev' => [],
    ];

    $project_root = $this->pathLocator->getProjectRoot();
    $info = $this->composerInspector->getRootPackageInfo($project_root);
    $installed_packages = $this->composerInspector->getInstalledPackagesList($project_root);
    foreach ($project_versions as $project_name => $version) {
      $package = $installed_packages->getPackageByDrupalProjectName($project_name);
      if (empty($package)) {
        throw new \InvalidArgumentException("The project $project_name is not a Drupal project known to Composer and cannot be updated.");
      }

      // We don't support updating install profiles.
      if ($package->type === 'drupal-profile') {
        throw new \InvalidArgumentException("The project $project_name cannot be updated because updating install profiles is not supported.");
      }

      $group = isset($info['devRequires'][$package->name]) ? 'dev' : 'production';
      $package_versions[$group][$package->name] = LegacyVersionUtility::convertToSemanticVersion($version);
    }

    // Ensure that package versions are available to pre-create event
    // subscribers. We can't use ::setMetadata() here because it requires the
    // stage to be claimed, but that only happens during ::create().
    $this->tempStore->set(static::TEMPSTORE_METADATA_KEY, [
      'packages' => $package_versions,
    ]);
    return $this->create();
  }

  /**
   * Returns the package versions that will be required during the update.
   *
   * @return string[][]
   *   An array with two sub-arrays: 'production' and 'dev'. Each is a set of
   *   package versions, where the keys are package names and the values are
   *   version constraints understood by Composer.
   */
  public function getPackageVersions(): array {
    return $this->getMetadata('packages');
  }

  /**
   * Stages the update.
   */
  public function stage(): void {
    $this->checkOwnership();

    // Convert an associative array of package versions, keyed by name, to
    // command-line arguments in the form `vendor/name:version`.
    $map = function (array $versions): array {
      $requirements = [];
      foreach ($versions as $package => $version) {
        $requirements[] = "$package:$version";
      }
      return $requirements;
    };
    $versions = array_map($map, $this->getPackageVersions());
    $this->require($versions['production'], $versions['dev']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFailureMarkerMessage(): TranslatableMarkup {
    return $this->t('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
  }

}
