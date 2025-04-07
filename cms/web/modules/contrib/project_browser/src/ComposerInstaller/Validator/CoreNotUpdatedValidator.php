<?php

namespace Drupal\project_browser\ComposerInstaller\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\PathLocator;
use Drupal\project_browser\ComposerInstaller\Installer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that Drupal core was not updated during project install.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class CoreNotUpdatedValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a CoreNotUpdatedValidator object.
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
   * Validates Drupal core was not updated during project install.
   *
   * @param \Drupal\package_manager\Event\PreApplyEvent $event
   *   The event object.
   */
  public function validateCoreNotUpdated(PreApplyEvent $event): void {
    $stage = $event->stage;
    if (!$stage instanceof Installer) {
      return;
    }
    $active_dir = $this->pathLocator->getProjectRoot();
    $stage_dir = $stage->getStageDirectory();
    $active_packages = $this->composerInspector->getInstalledPackagesList($active_dir);
    $staged_packages = $this->composerInspector->getInstalledPackagesList($stage_dir);
    $updated_packages = $staged_packages->getPackagesWithDifferentVersionsIn($active_packages);

    if (isset($updated_packages['drupal/core'])) {
      $event->addError([
        $this->t('Drupal core has been updated in the staging area, which is not allowed by Project Browser.'),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreApplyEvent::class => 'validateCoreNotUpdated',
    ];
  }

}
