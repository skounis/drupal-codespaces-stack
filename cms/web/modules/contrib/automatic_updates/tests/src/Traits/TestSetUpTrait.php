<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Traits;

use Drupal\package_manager\PathLocator;
use Drupal\Tests\package_manager\Traits\FixtureUtilityTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common functions to set up tests correctly.
 */
trait TestSetUpTrait {

  use FixtureUtilityTrait;

  /**
   * {@inheritdoc}
   */
  protected function installModulesFromClassProperty(ContainerInterface $container): void {
    $container->get('module_installer')->install([
      'package_manager_test_release_history',
      'package_manager_bypass',
    ]);
    $this->container = $container->get('kernel')->getContainer();

    // To prevent tests from using the real codebase for Composer we must set
    // the fixture path as early as possible. Automatic Updates will run
    // readiness checks when the module is installed so this must be done before
    // the parent class installs the modules needed for the test.
    $this->useFixtureDirectoryAsActive(__DIR__ . '/../../../package_manager/tests/fixtures/fake_site');

    // To prevent tests from making real requests to the Internet, use fake
    // release metadata that exposes a pretend Drupal 9.8.2 release.
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml');

    parent::installModulesFromClassProperty($container);
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
    $this->container->get(PathLocator::class)
      ->setPaths($active_dir, $active_dir . '/vendor', '', NULL);
  }

  /**
   * Sets the release metadata file to use when fetching available updates.
   *
   * @todo Remove this function with use of the trait from the Update module in
   *   https://drupal.org/i/3348234.
   *
   * @param string $file
   *   The path of the XML metadata file to use.
   */
  protected function setReleaseMetadata(string $file): void {
    $this->assertFileIsReadable($file);

    $this->config('update.settings')
      ->set('fetch.url', $this->baseUrl . '/test-release-history')
      ->save();

    [$project] = explode('.', basename($file, '.xml'), 2);
    $xml_map = $this->config('update_test.settings')->get('xml_map') ?? [];
    $xml_map[$project] = $file;
    $this->config('update_test.settings')
      ->set('xml_map', $xml_map)
      ->save();
  }

}
