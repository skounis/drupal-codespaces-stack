<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\UpdateStage;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\InstalledPackage;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates the staged Drupal projects.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class StagedProjectsValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly PathLocator $pathLocator,
    private readonly ComposerInspector $composerInspector,
  ) {}

  /**
   * Validates the staged packages.
   *
   * @param \Drupal\package_manager\Event\PreApplyEvent $event
   *   The event object.
   */
  public function validateStagedProjects(PreApplyEvent $event): void {
    $stage = $event->stage;
    // We only want to do this check if the stage belongs to Automatic Updates.
    if (!$stage instanceof UpdateStage) {
      return;
    }

    $active_list = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $stage_list = $this->composerInspector->getInstalledPackagesList($stage->getStageDirectory());

    $type_map = [
      'drupal-module' => $this->t('module'),
      'drupal-custom-module' => $this->t('custom module'),
      'drupal-theme' => $this->t('theme'),
      'drupal-custom-theme' => $this->t('custom theme'),
    ];
    $filter = function (InstalledPackage $package) use ($type_map): bool {
      return array_key_exists($package->type, $type_map);
    };
    $new_packages = $stage_list->getPackagesNotIn($active_list);
    $removed_packages = $active_list->getPackagesNotIn($stage_list);
    $updated_packages = $active_list->getPackagesWithDifferentVersionsIn($stage_list);

    // Check if any new Drupal projects were installed.
    if ($new_packages = array_filter($new_packages->getArrayCopy(), $filter)) {
      $new_packages_messages = [];

      foreach ($new_packages as $new_package) {
        $new_packages_messages[] = $this->t(
          "@type '@name' installed.",
          [
            '@type' => $type_map[$new_package->type],
            '@name' => $new_package->name,
          ]
        );
      }
      $new_packages_summary = $this->formatPlural(
        count($new_packages_messages),
        'The update cannot proceed because the following Drupal project was installed during the update.',
        'The update cannot proceed because the following Drupal projects were installed during the update.'
      );
      $event->addError($new_packages_messages, $new_packages_summary);
    }

    // Check if any Drupal projects were removed.
    if ($removed_packages = array_filter($removed_packages->getArrayCopy(), $filter)) {
      $removed_packages_messages = [];
      foreach ($removed_packages as $removed_package) {
        $removed_packages_messages[] = $this->t(
          "@type '@name' removed.",
          [
            '@type' => $type_map[$removed_package->type],
            '@name' => $removed_package->name,
          ]
        );
      }
      $removed_packages_summary = $this->formatPlural(
        count($removed_packages_messages),
        'The update cannot proceed because the following Drupal project was removed during the update.',
        'The update cannot proceed because the following Drupal projects were removed during the update.'
      );
      $event->addError($removed_packages_messages, $removed_packages_summary);
    }

    // Check if any Drupal projects were neither installed or removed, but had
    // their version numbers changed.
    if ($updated_packages = array_filter($updated_packages->getArrayCopy(), $filter)) {

      $version_change_messages = [];
      foreach ($updated_packages as $name => $updated_package) {
        $version_change_messages[] = $this->t(
          "@type '@name' from @active_version to @staged_version.",
          [
            '@type' => $type_map[$updated_package->type],
            '@name' => $updated_package->name,
            '@staged_version' => $stage_list[$name]->version,
            '@active_version' => $updated_package->version,
          ]
        );
      }
      $version_change_summary = $this->formatPlural(
        count($version_change_messages),
        'The update cannot proceed because the following Drupal project was unexpectedly updated. Only Drupal Core updates are currently supported.',
        'The update cannot proceed because the following Drupal projects were unexpectedly updated. Only Drupal Core updates are currently supported.'
      );
      $event->addError($version_change_messages, $version_change_summary);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PreApplyEvent::class][] = ['validateStagedProjects'];
    return $events;
  }

}
