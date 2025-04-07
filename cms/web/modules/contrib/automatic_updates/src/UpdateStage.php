<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\FailureMarker;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\StageBase;
use PhpTuf\ComposerStager\API\Core\BeginnerInterface;
use PhpTuf\ComposerStager\API\Core\CommitterInterface;
use PhpTuf\ComposerStager\API\Core\StagerInterface;
use PhpTuf\ComposerStager\API\Path\Factory\PathFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a service to perform updates.
 *
 * Currently, only updates to Drupal core are supported. This is done by
 * changing the constraint for either 'drupal/core' or 'drupal/core-recommended'
 * in the project-level composer.json. If neither package is directly required
 * in the project-level composer.json, a requirement will be added.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
class UpdateStage extends StageBase {

  /**
   * {@inheritdoc}
   */
  protected string $type = 'automatic_updates:attended';

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
   * @param int|null $timeout
   *   (optional) How long to allow the file copying operation to run before
   *   timing out, in seconds, or NULL to never time out. Defaults to 300
   *   seconds.
   *
   * @return string
   *   The unique ID of the stage.
   *
   * @throws \InvalidArgumentException
   *   Thrown if no project version for Drupal core is provided.
   */
  public function begin(array $project_versions, ?int $timeout = 300): string {
    if (count($project_versions) !== 1 || !array_key_exists('drupal', $project_versions)) {
      throw new \InvalidArgumentException("Currently only updates to Drupal core are supported.");
    }

    $package_versions = [
      'production' => [],
      'dev' => [],
    ];

    $project_root = $this->pathLocator->getProjectRoot();
    $info = $this->composerInspector->getRootPackageInfo($project_root);
    foreach ($this->composerInspector->getInstalledPackagesList($project_root)->getCorePackages() as $package) {
      $group = isset($info['devRequires'][$package->name]) ? 'dev' : 'production';
      $package_versions[$group][$package->name] = $project_versions['drupal'];
    }

    // Ensure that package versions are available to pre-create event
    // subscribers. We can't use ::setMetadata() here because it requires the
    // stage to be claimed, but that only happens during ::create().
    $this->tempStore->set(static::TEMPSTORE_METADATA_KEY, [
      'packages' => $package_versions,
    ]);
    return $this->create($timeout);
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
  public function stage(?int $timeout = 300): void {
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
    $this->require($versions['production'], $versions['dev'], $timeout);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFailureMarkerMessage(): TranslatableMarkup {
    return $this->t('Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup.');
  }

}
