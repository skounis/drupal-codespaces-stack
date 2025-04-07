<?php

namespace Drupal\project_browser\ComposerInstaller\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreRequireEvent;
use Drupal\package_manager\PathLocator;
use Drupal\project_browser\ComposerInstaller\Installer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that packages to be installed are not already installed.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class PackageNotInstalledValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a PackageNotInstalledValidator object.
   *
   * @param \Drupal\package_manager\PathLocator $pathLocator
   *   The path locator service.
   * @param \Drupal\package_manager\ComposerInspector $composerInspector
   *   The Composer inspector service.
   */
  public function __construct(
    private readonly PathLocator $pathLocator,
    private readonly ComposerInspector $composerInspector,
  ) {}

  /**
   * Validates that packages are not already installed with composer.
   *
   * @param \Drupal\package_manager\Event\PreRequireEvent $event
   *   The event object.
   */
  public function validatePackagesNotAlreadyInstalled(PreRequireEvent $event): void {
    $stage = $event->stage;
    if (!$stage instanceof Installer) {
      return;
    }

    $installed_packages = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    // Assuming project browser cannot install dev releases, since we are not
    // calling $event->getDevPackages() for now.
    $required_packages = $event->getRuntimePackages();
    $already_installed_packages = [];

    foreach (array_keys($required_packages) as $required_package) {
      if (isset($installed_packages[$required_package])) {
        $already_installed_packages[] = $required_package;
      }
    }

    if (!empty($already_installed_packages)) {
      $event->addError([$this->formatPlural(count($already_installed_packages), 'The following package is already installed: @packages', 'The following packages are already installed: @packages', ['@packages' => implode(', ', $already_installed_packages)])]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreRequireEvent::class => 'validatePackagesNotAlreadyInstalled',
    ];
  }

}
