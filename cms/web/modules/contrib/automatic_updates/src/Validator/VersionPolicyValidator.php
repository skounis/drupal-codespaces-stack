<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionNotPreRelease;
use Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionStable;
use Drupal\Component\Utility\NestedArray;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ProjectInfo;
use Drupal\automatic_updates\UpdateStage;
use Drupal\automatic_updates\Validator\VersionPolicy\ForbidDowngrade;
use Drupal\automatic_updates\Validator\VersionPolicy\ForbidMinorUpdates;
use Drupal\automatic_updates\Validator\VersionPolicy\MajorVersionMatch;
use Drupal\automatic_updates\Validator\VersionPolicy\StableReleaseInstalled;
use Drupal\automatic_updates\Validator\VersionPolicy\ForbidDevSnapshot;
use Drupal\automatic_updates\Validator\VersionPolicy\SupportedBranchInstalled;
use Drupal\automatic_updates\Validator\VersionPolicy\TargetSecurityRelease;
use Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionInstallable;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\StageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates the installed and target versions of Drupal before an update.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class VersionPolicyValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly CronUpdateRunner $cronUpdateRunner,
    private readonly ClassResolverInterface $classResolver,
    private readonly PathLocator $pathLocator,
    private readonly ComposerInspector $composerInspector,
  ) {}

  /**
   * Validates a target version of Drupal core.
   *
   * @param \Drupal\automatic_updates\UpdateStage $stage
   *   The update stage which will perform the update.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if it is not known.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The error messages returned from the first policy rule which rejected
   *   the given target version.
   *
   * @see \Drupal\automatic_updates\Validator\VersionPolicy\RuleBase::validate()
   */
  public function validateVersion(UpdateStage $stage, ?string $target_version): array {
    // Check that the installed version of Drupal isn't a dev snapshot.
    $rules = [
      ForbidDevSnapshot::class,
    ];

    // If the target version is known, it must conform to a few basic rules.
    if ($target_version) {
      // The target version must be newer than the installed version...
      $rules[] = ForbidDowngrade::class;
      // ...and in the same major version as the installed version...
      $rules[] = MajorVersionMatch::class;
      // ...and it must be a known, secure, installable release...
      $rules[] = TargetVersionInstallable::class;
      // @todo Remove the need to check for the stage instance in
      //   https://drupal.org/i/3398782.
      if ($stage->getType() !== 'automatic_updates:unattended') {
        // ...and must be either a release candidate, or stable.
        $rules[] = TargetVersionNotPreRelease::class;
      }
    }

    // If this is a cron update, we may need to do additional checks.
    if ($stage->getType() === 'automatic_updates:unattended') {
      $mode = $this->cronUpdateRunner->getMode();

      // @todo Remove the need to check if cron updates are disabled in
      //   https://drupal.org/i/3398782.
      if ($mode !== CronUpdateRunner::DISABLED) {
        // If cron updates are enabled, the installed version must be stable;
        // no alphas, betas, or RCs.
        $rules[] = StableReleaseInstalled::class;
        // It must also be in a supported branch.
        $rules[] = SupportedBranchInstalled::class;

        // If the target version is known, more rules apply.
        if ($target_version) {
          // The target version must be stable too...
          $rules[] = TargetVersionStable::class;
          // ...and it must be in the same minor as the installed version.
          $rules[] = ForbidMinorUpdates::class;

          // If only security updates are allowed during cron, the target
          // version must be a security release.
          if ($mode === CronUpdateRunner::SECURITY) {
            $rules[] = TargetSecurityRelease::class;
          }
        }
      }
    }

    $installed_version = $this->getInstalledVersion();
    $available_releases = $this->getAvailableReleases($stage);

    // Let all the rules flag whatever messages they need to.
    $messages = [];
    foreach ($rules as $rule) {
      $messages[$rule] = $this->classResolver->getInstanceFromDefinition($rule)
        ->validate($installed_version, $target_version, $available_releases);
    }
    // Remove any messages that are superseded by other, more specific ones.
    $filtered_rule_messages = array_filter($messages, fn ($rule) => !self::isRuleSuperseded($rule, $messages), ARRAY_FILTER_USE_KEY);
    // Collapse all the rules' messages into a single array.
    return NestedArray::mergeDeepArray($filtered_rule_messages);
  }

  /**
   * Check if a given rule's messages are superseded by a more specific rule.
   *
   * @param string $rule
   *   The rule to check.
   * @param array[] $rule_messages
   *   The messages that were returned by the various rules, keyed by the name
   *   of the rule that returned them.
   *
   * @return bool
   *   TRUE if the given rule is superseded by another rule, FALSE otherwise.
   */
  private static function isRuleSuperseded(string $rule, array $rule_messages): bool {
    // Some rules' messages are more specific than other rules' messages. For
    // example, if the message "… automatic updates from one major version to
    // another are not supported" is returned, then the message "… not in the
    // list of installable releases" is not needed because the new major version
    // will not be in the list of installable releases. The keys of this array
    // are the rules which supersede messages from the values, which are the
    // less specific rules.
    $more_specific_rule_sets = [
      ForbidDowngrade::class => [TargetVersionInstallable::class, MajorVersionMatch::class],
      ForbidDevSnapshot::class => [StableReleaseInstalled::class],
      MajorVersionMatch::class => [TargetVersionInstallable::class],
      ForbidMinorUpdates::class => [TargetVersionInstallable::class],
      TargetVersionStable::class => [TargetVersionNotPreRelease::class],
    ];
    foreach ($more_specific_rule_sets as $more_specific_rule => $less_specific_rules) {
      // If the more specific rule flagged any messages, the given rule is
      // superseded.
      if (!empty($rule_messages[$more_specific_rule]) && in_array($rule, $less_specific_rules, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks that the target version of Drupal is valid.
   *
   * @param \Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   */
  public function checkVersion(StageEvent $event): void {
    $stage = $event->stage;

    // Only do these checks for automatic updates.
    if (!$stage instanceof UpdateStage) {
      return;
    }
    $target_version = $this->getTargetVersion($event);

    $messages = $this->validateVersion($stage, $target_version);
    if ($messages) {
      $installed_version = $this->getInstalledVersion();

      if ($target_version) {
        $summary = $this->t('Updating from Drupal @installed_version to @target_version is not allowed.', [
          '@installed_version' => $installed_version,
          '@target_version' => $target_version,
        ]);
      }
      else {
        $summary = $this->t('Updating from Drupal @installed_version is not allowed.', [
          '@installed_version' => $installed_version,
        ]);
      }
      $event->addError($messages, $summary);
    }
  }

  /**
   * Returns the target version of Drupal core.
   *
   * @param \Drupal\package_manager\Event\StageEvent $event
   *   The event object.
   *
   * @return string|null
   *   The target version of Drupal core, or NULL if it could not be determined
   *   during a status check.
   *
   * @throws \LogicException
   *   Thrown if the target version cannot be determined due to unexpected
   *   conditions. This can happen if, during a stage life cycle event (i.e.,
   *   NOT a status check), the event or update stage does not have a list of
   *   desired package versions, or the list of package versions does not
   *   include any Drupal core packages.
   */
  private function getTargetVersion(StageEvent $event): ?string {
    $stage = $event->stage;

    // If we're not doing a status check, we expect the stage to have been
    // created, and the requested package versions recorded.
    if (!$event instanceof StatusCheckEvent) {
      $package_versions = $stage->getPackageVersions()['production'];
    }

    $unknown_target = new \LogicException('The target version of Drupal core could not be determined.');

    if (isset($package_versions)) {
      $core_package_name = $this->getCorePackageName();

      if ($core_package_name && array_key_exists($core_package_name, $package_versions)) {
        return $package_versions[$core_package_name];
      }
      else {
        throw $unknown_target;
      }
    }
    elseif ($event instanceof StatusCheckEvent) {
      if ($stage->getType() === 'automatic_updates:unattended') {
        $target_release = $stage->getTargetRelease();
        if ($target_release) {
          return $target_release->getVersion();
        }
      }
      return NULL;
    }
    // If we got here, something has gone very wrong.
    throw $unknown_target;
  }

  /**
   * Returns the available releases of Drupal core for a given update stage.
   *
   * @param \Drupal\automatic_updates\UpdateStage $stage
   *   The update stage which will perform the update.
   *
   * @return \Drupal\update\ProjectRelease[]
   *   The available releases of Drupal core, keyed by version number and in
   *   descending order (i.e., newest first). Will be in ascending order (i.e.,
   *   oldest first) if $stage is the cron update runner.
   *
   * @see \Drupal\package_manager\ProjectInfo::getInstallableReleases()
   */
  private function getAvailableReleases(UpdateStage $stage): array {
    $project_info = new ProjectInfo('drupal');
    $available_releases = $project_info->getInstallableReleases() ?? [];

    if ($stage->getType() === 'automatic_updates:unattended') {
      $available_releases = array_reverse($available_releases);
    }
    return $available_releases;
  }

  /**
   * Returns the currently installed version of Drupal core.
   *
   * @return string|null
   *   The currently installed version of Drupal core, or NULL if it could not
   *   be determined.
   */
  private function getInstalledVersion(): ?string {
    return (new ProjectInfo('drupal'))->getInstalledVersion();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreCreateEvent::class => 'checkVersion',
      StatusCheckEvent::class => 'checkVersion',
    ];
  }

  /**
   * Returns the name of the first known installed core package.
   *
   * This does NOT include dev packages like `drupal/core-dev` and
   * `drupal/core-dev-pinned`.
   *
   * @return string|bool
   *   The name of the first known installed core package (most likely
   *   `drupal/core` or `drupal/core-recommended`), or FALSE if none is found.
   */
  private function getCorePackageName(): string|bool {
    $project_root = $this->pathLocator->getProjectRoot();

    $core_packages = $this->composerInspector->getInstalledPackagesList($project_root)
      ->getCorePackages(FALSE)
      ->getArrayCopy();

    return key($core_packages) ?? FALSE;
  }

}
