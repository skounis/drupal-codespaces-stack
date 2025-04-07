<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Traits;

use Drupal\package_manager\PathLocator;
use Drupal\package_manager_bypass\MockPathLocator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This copies methods from Package Manager's FixtureUtilityTrait.
 *
 * Package Manager's FixtureUtilityTrait is internal, so a version of that is
 * provided here, truncated to use only the methods needed by Project Browser's
 * tests.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
trait PackageManagerFixtureUtilityTrait {

  /**
   * Initializes Package Manager.
   */
  protected function initPackageManager(): void {
    $pm_path = $this->container->get('extension.list.module')->getPath('package_manager');
    $this->useFixtureDirectoryAsActive($pm_path . '/tests/fixtures/fake_site');
  }

  /**
   * Sets a fixture directory to use as the active directory.
   *
   * @param string $fixture_directory
   *   The fixture directory.
   */
  protected function useFixtureDirectoryAsActive(string $fixture_directory): void {
    // Create a temporary directory from our fixture directory that will be
    // unique for each test run. This will enable changing files in the
    // directory and not affect other tests.
    $active_dir = $this->copyFixtureToTempDirectory($fixture_directory);
    $path_locator = $this->container->get(PathLocator::class);
    assert($path_locator instanceof MockPathLocator);
    $path_locator->setPaths($active_dir, $active_dir . '/vendor', '', NULL);
  }

  /**
   * Copies a fixture directory to a temporary directory.
   *
   * @param string $fixture_directory
   *   The fixture directory.
   *
   * @return string
   *   The temporary directory.
   */
  protected function copyFixtureToTempDirectory(string $fixture_directory): string {
    $temp_directory = $this->root . DIRECTORY_SEPARATOR . $this->siteDirectory . DIRECTORY_SEPARATOR . $this->randomMachineName(20);
    static::copyFixtureFilesTo($fixture_directory, $temp_directory);
    return $temp_directory;
  }

  /**
   * Mirrors a fixture directory to the given path.
   *
   * Files not in the source fixture directory will not be deleted from
   * destination directory. After copying the files to the destination directory
   * the files and folders will be converted so that can be used in the tests.
   * The conversion includes:
   * - Renaming '_git' directories to '.git'
   * - Renaming files ending in '.info.yml.hide' to remove '.hide'.
   *
   * @param string $source_path
   *   The source path.
   * @param string $destination_path
   *   The path to which the fixture files should be mirrored.
   */
  protected static function copyFixtureFilesTo(string $source_path, string $destination_path): void {
    (new Filesystem())->mirror($source_path, $destination_path, NULL, [
      'override' => TRUE,
      'delete' => FALSE,
    ]);
  }

}
