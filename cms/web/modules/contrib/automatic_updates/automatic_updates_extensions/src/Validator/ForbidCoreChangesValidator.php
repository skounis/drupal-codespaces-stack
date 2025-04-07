<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions\Validator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\InstalledPackagesList;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that no changes were made to Drupal Core packages.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class ForbidCoreChangesValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly PathLocator $pathLocator,
    private readonly ComposerInspector $composerInspector,
  ) {}

  /**
   * Validates the staged packages.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent|\Drupal\package_manager\Event\PreApplyEvent $event
   *   The event object.
   */
  public function validateStagedCorePackages(StatusCheckEvent|PreApplyEvent $event): void {
    $stage = $event->stage;
    // We only want to do this check if the stage belongs to Automatic Updates
    // Extensions.
    if ($stage->getType() !== 'automatic_updates_extensions:attended' || !$stage->stageDirectoryExists()) {
      return;
    }
    $active_core_packages = $this->getInstalledCorePackages($this->pathLocator->getProjectRoot());
    $stage_core_packages = $this->getInstalledCorePackages($stage->getStageDirectory());

    $new_packages = $stage_core_packages->getPackagesNotIn($active_core_packages);
    $removed_packages = $active_core_packages->getPackagesNotIn($stage_core_packages);
    $changed_packages = $active_core_packages->getPackagesWithDifferentVersionsIn($stage_core_packages);

    $error_messages = [];
    foreach ($new_packages as $new_package) {
      $error_messages[] = $this->t("'@name' installed.", ['@name' => $new_package->name]);
    }
    foreach ($removed_packages as $removed_package) {
      $error_messages[] = $this->t("'@name' removed.", ['@name' => $removed_package->name]);
    }
    foreach ($changed_packages as $name => $updated_package) {
      $error_messages[] = $this->t(
        "'@name' version changed from @active_version to @staged_version.",
        [
          '@name' => $updated_package->name,
          '@staged_version' => $stage_core_packages[$name]->version,
          '@active_version' => $updated_package->version,
        ]
      );

    }
    if ($error_messages) {
      $event->addError($error_messages, $this->t(
        'Updating Drupal Core while updating extensions is currently not supported. Use <a href=":url">this form</a> to update Drupal core. The following changes were made to the Drupal core packages:',
        [':url' => Url::fromRoute('update.report_update')->toString()]
      ));
    }
  }

  /**
   * Gets all the installed core packages for a given project root.
   *
   * This method differs from
   * \Drupal\package_manager\ComposerInspector::getInstalledPackagesList in that
   * it ensures that the 'drupal/core' is included in the list if present.
   *
   * @param string $composer_root
   *   The path to the composer root.
   *
   * @return \Drupal\package_manager\InstalledPackagesList
   *   The installed core packages.
   */
  private function getInstalledCorePackages(string $composer_root): InstalledPackagesList {
    $installed_package_list = $this->composerInspector->getInstalledPackagesList($composer_root);
    $core_packages = $installed_package_list->getCorePackages();
    if (isset($installed_package_list['drupal/core']) && !isset($core_packages['drupal/core'])) {
      $core_packages = new InstalledPackagesList(array_merge($core_packages->getArrayCopy(), ['drupal/core' => $installed_package_list['drupal/core']]));
    }
    return $core_packages;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[StatusCheckEvent::class][] = ['validateStagedCorePackages'];
    $events[PreApplyEvent::class][] = ['validateStagedCorePackages'];
    return $events;
  }

}
