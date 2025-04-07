<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\ConsoleUpdateStage;
use Drupal\automatic_updates\UpdateStage;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicyValidator
 * @group automatic_updates
 * @internal
 */
class VersionPolicyValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Data provider for testStatusCheck().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerStatusCheckSpecific(): array {
    $metadata_dir = __DIR__ . '/../../../../package_manager/tests/fixtures/release-history';

    return [
      // This case proves that, if a stable release is installed, there is no
      // error generated when if the next available release is a normal (i.e.,
      // non-security) release. If unattended updates are only enabled for
      // security releases, the next available release will be ignored, and
      // therefore generate no validation errors, because it's not a security
      // release.
      'update to normal release' => [
        '9.8.1',
        NULL,
        "$metadata_dir/drupal.9.8.2.xml",
        [CronUpdateRunner::DISABLED, CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [],
      ],
      // These three cases prove that updating from an unsupported minor version
      // will raise an error if unattended updates are enabled. Furthermore, if
      // an error is raised, the messaging will vary depending on whether
      // attended updates across minor versions are allowed. (Note that the
      // target version will not be automatically detected because the release
      // metadata used in these cases doesn't have any 9.7.x releases.)
      'update from unsupported minor, cron disabled' => [
        '9.7.1',
        NULL,
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'update from unsupported minor, cron enabled, minor updates forbidden' => [
        '9.7.1',
        NULL,
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('The currently installed version of Drupal core, 9.7.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.'),
          t('See the <a href="/admin/reports/updates">available updates page</a> for available updates.'),
        ],
      ],
      'update from unsupported minor, cron enabled, minor updates allowed' => [
        '9.7.1',
        NULL,
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('The currently installed version of Drupal core, 9.7.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.'),
          t('Use the <a href="/admin/modules/update">update form</a> to update to a supported version.'),
        ],
        TRUE,
      ],
    ];
  }

  /**
   * Data provider for testStatusCheck() and testCronPreCreate().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerGeneric(): array {
    $metadata_dir = __DIR__ . '/../../../../package_manager/tests/fixtures/release-history';

    return [
      // Updating from a dev, alpha, beta, or RC release is not allowed during
      // cron. The first case is a control to prove that a legitimate
      // patch-level update from a stable release never raises an error.
      'stable release installed' => [
        '9.8.0',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED, CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [],
      ],
      // This case proves that updating from a dev snapshot is never allowed,
      // regardless of configuration.
      'dev snapshot installed' => [
        '9.8.0-dev',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED, CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'),
        ],
      ],
      // The next six cases prove that updating from an alpha, beta, or RC
      // release raises an error if unattended updates are enabled.
      'alpha installed, cron disabled' => [
        '9.8.0-alpha1',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'alpha installed, cron enabled' => [
        '9.8.0-alpha1',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('Drupal cannot be automatically updated during cron from its current version, 9.8.0-alpha1, because it is not a stable version.'),
        ],
      ],
      'beta installed, cron disabled' => [
        '9.8.0-beta2',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'beta installed, cron enabled' => [
        '9.8.0-beta2',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('Drupal cannot be automatically updated during cron from its current version, 9.8.0-beta2, because it is not a stable version.'),
        ],
      ],
      'rc installed, cron disabled' => [
        '9.8.0-rc3',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'rc installed, cron enabled' => [
        '9.8.0-rc3',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('Drupal cannot be automatically updated during cron from its current version, 9.8.0-rc3, because it is not a stable version.'),
        ],
      ],
    ];
  }

  /**
   * Tests target version validation during status checks.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core.
   * @param string $release_metadata
   *   The path of the core release metadata to serve to the update system.
   * @param string[] $cron_modes
   *   The modes for unattended updates. Can contain any of
   *   \Drupal\automatic_updates\CronUpdateRunner::DISABLED,
   *   \Drupal\automatic_updates\CronUpdateRunner::SECURITY, and
   *   \Drupal\automatic_updates\CronUpdateRunner::ALL.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $expected_validation_messages
   *   The expected validation messages.
   * @param bool $allow_minor_updates
   *   (optional) Whether or not attended updates across minor updates are
   *   allowed. Defaults to FALSE.
   *
   * @dataProvider providerGeneric
   * @dataProvider providerStatusCheckSpecific
   */
  public function testStatusCheck(string $installed_version, ?string $target_version, string $release_metadata, array $cron_modes, array $expected_validation_messages, bool $allow_minor_updates = FALSE): void {
    $this->setCoreVersion($installed_version);
    $this->setReleaseMetadata(['drupal' => $release_metadata]);

    foreach ($cron_modes as $cron_mode) {
      $this->config('automatic_updates.settings')
        ->set('unattended.level', $cron_mode)
        ->set('allow_core_minor_updates', $allow_minor_updates)
        ->save();

      $expected_results = [];
      if ($expected_validation_messages) {
        // If we're doing a status check, the stage isn't created, and the
        // requested package versions are not recorded during begin(), so the
        // error message won't contain the target version.
        $expected_results[] = static::createVersionPolicyValidationResult($installed_version, NULL, $expected_validation_messages);
      }
      $this->assertCheckerResultsFromManager($expected_results, TRUE);
    }
  }

  /**
   * Data provider for testCronPreCreate().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerCronPreCreateSpecific(): array {
    $metadata_dir = __DIR__ . '/../../../../package_manager/tests/fixtures/release-history';

    return [
      // The next three cases prove that update to an alpha, beta, or RC release
      // doesn't raise any error if unattended updates are disabled.
      'update to alpha, cron disabled' => [
        '9.8.0',
        '9.8.1-alpha1',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'update to beta, cron disabled' => [
        '9.8.0',
        '9.8.1-beta2',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      'update to rc, cron disabled' => [
        '9.8.0',
        '9.8.1-rc3',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::DISABLED],
        [],
      ],
      // This case proves that, if a stable release is installed, there is an
      // error generated when if the next available release is a normal (i.e.,
      // non-security) release, if unattended updates are only enabled for
      // security releases.
      'update to normal release, cron enabled for security releases' => [
        '9.8.1',
        '9.8.2',
        "$metadata_dir/drupal.9.8.2.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron from 9.8.1 to 9.8.2 because 9.8.2 is not a security release.'),
        ],
      ],
      // The next three cases prove that normal (i.e., non-security) update to
      // an alpha, beta, or RC release raises multiple errors if unattended
      // updates are enabled only for security releases.
      'normal update to alpha, cron enabled for security releases' => [
        '9.8.0',
        '9.8.1-alpha1',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-alpha1, because it is not a stable version.'),
          t('Drupal cannot be automatically updated during cron from 9.8.0 to 9.8.1-alpha1 because 9.8.1-alpha1 is not a security release.'),
        ],
      ],
      'normal update to beta, cron enabled for security releases' => [
        '9.8.0',
        '9.8.1-beta2',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-beta2, because it is not a stable version.'),
          t('Drupal cannot be automatically updated during cron from 9.8.0 to 9.8.1-beta2 because 9.8.1-beta2 is not a security release.'),
        ],
      ],
      'normal update to rc, cron enabled for security releases' => [
        '9.8.0',
        '9.8.1-rc3',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-rc3, because it is not a stable version.'),
          t('Drupal cannot be automatically updated during cron from 9.8.0 to 9.8.1-rc3 because 9.8.1-rc3 is not a security release.'),
        ],
      ],
      // The next three cases prove that normal (i.e., non-security) minor
      // updates to an alpha, beta, or RC release raises multiple errors if
      // unattended updates are enabled only for security releases.
      'update to alpha of next minor, cron enabled for security releases, minor updates forbidden' => [
        '9.7.0',
        '9.8.1-alpha1',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-alpha1, because it is not a stable version.'),
          t('Drupal cannot be automatically updated from 9.7.0 to 9.8.1-alpha1 because automatic updates from one minor version to another are not supported during cron.'),
          t('Drupal cannot be automatically updated during cron from 9.7.0 to 9.8.1-alpha1 because 9.8.1-alpha1 is not a security release.'),
        ],
      ],
      'update to beta of next minor, cron enabled for security releases, minor updates forbidden' => [
        '9.7.0',
        '9.8.1-beta2',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-beta2, because it is not a stable version.'),
          t('Drupal cannot be automatically updated from 9.7.0 to 9.8.1-beta2 because automatic updates from one minor version to another are not supported during cron.'),
          t('Drupal cannot be automatically updated during cron from 9.7.0 to 9.8.1-beta2 because 9.8.1-beta2 is not a security release.'),
        ],
      ],
      'update to rc of next minor, cron enabled for security releases, minor updates forbidden' => [
        '9.7.0',
        '9.8.1-rc3',
        "$metadata_dir/drupal.9.8.1-extra.xml",
        [CronUpdateRunner::SECURITY],
        [
          t('Drupal cannot be automatically updated during cron to the recommended version, 9.8.1-rc3, because it is not a stable version.'),
          t('Drupal cannot be automatically updated from 9.7.0 to 9.8.1-rc3 because automatic updates from one minor version to another are not supported during cron.'),
          t('Drupal cannot be automatically updated during cron from 9.7.0 to 9.8.1-rc3 because 9.8.1-rc3 is not a security release.'),
        ],
      ],
      // These three cases prove that updating from an unsupported minor version
      // will raise an error for unattended updates, if unattended updates are
      // enabled. Furthermore, if an error is raised, the messaging will vary
      // depending on whether attended updates across minor versions are
      // allowed. (Note that the target version will not be automatically
      // detected because the release metadata used in these cases doesn't have
      // any 9.7.x releases.)
      'update from unsupported minor, cron disabled, minor updates forbidden' => [
        '9.7.1',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::DISABLED],
        [
          t('Drupal cannot be automatically updated from 9.7.1 to 9.8.1 because automatic updates from one minor version to another are not supported.'),
        ],
      ],
      'update from unsupported minor, cron enabled, minor updates forbidden' => [
        '9.7.1',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('The currently installed version of Drupal core, 9.7.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.'),
          t('See the <a href="/admin/reports/updates">available updates page</a> for available updates.'),
          t('Drupal cannot be automatically updated from 9.7.1 to 9.8.1 because automatic updates from one minor version to another are not supported during cron.'),
        ],
      ],
      'update from unsupported minor, cron enabled, minor updates allowed' => [
        '9.7.1',
        '9.8.1',
        "$metadata_dir/drupal.9.8.1-security.xml",
        [CronUpdateRunner::SECURITY, CronUpdateRunner::ALL],
        [
          t('The currently installed version of Drupal core, 9.7.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.'),
          t('Use the <a href="/admin/modules/update">update form</a> to update to a supported version.'),
          t('Drupal cannot be automatically updated from 9.7.1 to 9.8.1 because automatic updates from one minor version to another are not supported during cron.'),
        ],
        TRUE,
      ],
    ];
  }

  /**
   * Tests target version validation during pre-create.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string $target_version
   *   The target version of Drupal core.
   * @param string $release_metadata
   *   The path of the core release metadata to serve to the update system.
   * @param string[] $cron_modes
   *   The modes for unattended updates. Can contain any of
   *   \Drupal\automatic_updates\CronUpdateRunner::DISABLED,
   *   \Drupal\automatic_updates\CronUpdateRunner::SECURITY, and
   *   \Drupal\automatic_updates\CronUpdateRunner::ALL.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $expected_validation_messages
   *   The expected validation messages.
   * @param bool $allow_minor_updates
   *   (optional) Whether or not attended updates across minor updates are
   *   allowed. Defaults to FALSE.
   *
   * @dataProvider providerGeneric
   * @dataProvider providerCronPreCreateSpecific
   */
  public function testCronPreCreate(string $installed_version, string $target_version, string $release_metadata, array $cron_modes, array $expected_validation_messages, bool $allow_minor_updates = FALSE): void {
    $this->setCoreVersion($installed_version);
    $this->setReleaseMetadata(['drupal' => $release_metadata]);

    // On pre-create, make the stage think that we're updating
    // drupal/core-recommended to $target_version. We need to do this to test
    // version validation during pre-create of an unattended update. We can't
    // use StageFixtureManipulator::setCorePackageVersion() for this, because
    // that would get executed after pre-create.
    // @see \Drupal\automatic_updates\Validator\VersionPolicyValidator::validateVersion()
    $this->addEventTestListener(function (PreCreateEvent $event) use ($target_version): void {
      /** @var \Drupal\automatic_updates\ConsoleUpdateStage $stage */
      $stage = $event->stage;
      $stage->setMetadata('packages', [
        'production' => [
          'drupal/core-recommended' => $target_version,
        ],
      ]);
    }, PreCreateEvent::class);

    $expected_results = [];
    if ($expected_validation_messages) {
      $expected_results[] = static::createVersionPolicyValidationResult($installed_version, $target_version, $expected_validation_messages);
    }

    foreach ($cron_modes as $cron_mode) {
      $this->config('automatic_updates.settings')
        ->set('unattended.level', $cron_mode)
        ->set('allow_core_minor_updates', $allow_minor_updates)
        ->save();

      $stage = $this->container->get(ConsoleUpdateStage::class);
      try {
        $stage->create();
        // If we did not get an exception, ensure we didn't expect any results.
        $this->assertEmpty($expected_results);
      }
      catch (StageEventException $e) {
        $this->assertExpectedResultsFromException($expected_results, $e);
      }
      finally {
        $stage->destroy(TRUE);
      }
    }
  }

  /**
   * Data provider for testApi().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerApi(): array {
    $metadata_dir = __DIR__ . '/../../../../package_manager/tests/fixtures/release-history';

    return [
      'valid target, dev snapshot installed' => [
        '9.8.0-dev',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '9.8.1'],
        [
          static::createVersionPolicyValidationResult('9.8.0-dev', '9.8.1', [
            t('Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'),
          ]),
        ],
      ],
      'unsupported target, minor version upgrade' => [
        '9.7.1',
        "$metadata_dir/drupal.9.8.2-unsupported_unpublished.xml",
        ['drupal' => '9.8.1'],
        [
          static::createVersionPolicyValidationResult('9.7.1', '9.8.1', [
            t('Drupal cannot be automatically updated from 9.7.1 to 9.8.1 because automatic updates from one minor version to another are not supported.'),
          ]),
        ],
      ],
      'unsupported target, major version upgrade' => [
        '8.9.1',
        "$metadata_dir/drupal.9.8.2-unsupported_unpublished.xml",
        ['drupal' => '9.8.1'],
        [
          static::createVersionPolicyValidationResult('8.9.1', '9.8.1', [
            t('Drupal cannot be automatically updated from 8.9.1 to 9.8.1 because automatic updates from one major version to another are not supported.'),
          ]),
        ],
      ],
      // The following cases are used to test every combination if a dev
      // snapshot is installed.
      'insecure target, dev snapshot installed' => [
        '9.8.0-dev',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '9.8.0'],
        [
          static::createVersionPolicyValidationResult('9.8.0-dev', '9.8.0', [
            t('Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'),
            t('Cannot update Drupal core to 9.8.0 because it is not in the list of installable releases.'),
          ]),
        ],
      ],
      'downgrade major, dev snapshot installed' => [
        '9.8.0-dev',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '8.7.1'],
        [
          static::createVersionPolicyValidationResult('9.8.0-dev', '8.7.1', [
            t('Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'),
            t('Update version 8.7.1 is lower than 9.8.0-dev, downgrading is not supported.'),
          ]),
        ],
      ],
      'downgrade minor, dev snapshot installed' => [
        '9.8.0-dev',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '9.7.0'],
        [
          static::createVersionPolicyValidationResult('9.8.0-dev', '9.7.0', [
            t('Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'),
            t('Update version 9.7.0 is lower than 9.8.0-dev, downgrading is not supported.'),
          ]),
        ],
      ],
      'patch downgrade, dev snapshot installed' => [
        '9.8.1-dev',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '9.8.0'],
        [
          static::createVersionPolicyValidationResult('9.8.1-dev', '9.8.0', [
            t('Drupal cannot be automatically updated from the installed version, 9.8.1-dev, because automatic updates from a dev version to any other version are not supported.'),
            t('Update version 9.8.0 is lower than 9.8.1-dev, downgrading is not supported.'),
          ]),
        ],
      ],
      // The following cases can only happen by explicitly supplying the
      // update stage with an invalid target version.
      'downgrade' => [
        '9.8.1',
        "$metadata_dir/drupal.9.8.2.xml",
        ['drupal' => '9.8.0'],
        [
          static::createVersionPolicyValidationResult('9.8.1', '9.8.0', [
            t('Update version 9.8.0 is lower than 9.8.1, downgrading is not supported.'),
          ]),
        ],
      ],
      'major version upgrade' => [
        '8.9.1',
        "$metadata_dir/drupal.9.8.2.xml",
        ['drupal' => '9.8.2'],
        [
          static::createVersionPolicyValidationResult('8.9.1', '9.8.2', [
            t('Drupal cannot be automatically updated from 8.9.1 to 9.8.2 because automatic updates from one major version to another are not supported.'),
          ]),
        ],
      ],
      'unsupported target version' => [
        '9.8.0',
        "$metadata_dir/drupal.9.8.2-unsupported_unpublished.xml",
        ['drupal' => '9.8.1'],
        [
          static::createVersionPolicyValidationResult('9.8.0', '9.8.1', [
            t('Cannot update Drupal core to 9.8.1 because it is not in the list of installable releases.'),
          ]),
        ],
      ],
      // This case proves that an attended update to a normal non-security
      // release is allowed regardless of how cron is configured.
      'attended update to normal release' => [
        '9.8.1',
        "$metadata_dir/drupal.9.8.2.xml",
        ['drupal' => '9.8.2'],
        [],
      ],
      // These two cases prove that updating across minor versions of Drupal
      // core is only allowed for attended updates when a specific configuration
      // flag is set.
      'attended update to next minor not allowed' => [
        '9.7.9',
        "$metadata_dir/drupal.9.8.2.xml",
        ['drupal' => '9.8.2'],
        [
          static::createVersionPolicyValidationResult('9.7.9', '9.8.2', [
            t('Drupal cannot be automatically updated from 9.7.9 to 9.8.2 because automatic updates from one minor version to another are not supported.'),
          ]),
        ],
      ],
      'attended update to next minor allowed' => [
        '9.7.9',
        "$metadata_dir/drupal.9.8.2.xml",
        ['drupal' => '9.8.2'],
        [],
        TRUE,
      ],
      // If attended updates across minor versions are allowed, it's okay to
      // update from an unsupported minor version.
      'attended update from unsupported minor allowed' => [
        '9.7.9',
        "$metadata_dir/drupal.9.8.1-security.xml",
        ['drupal' => '9.8.1'],
        [],
        TRUE,
      ],
    ];
  }

  /**
   * Tests validation of explicitly specified target versions.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string $release_metadata
   *   The path of the core release metadata to serve to the update system.
   * @param string[] $project_versions
   *   The desired project versions that should be passed to the update stage.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   * @param bool $allow_minor_updates
   *   (optional) Whether to allow attended updates across minor versions.
   *   Defaults to FALSE.
   *
   * @dataProvider providerApi
   */
  public function testApi(string $installed_version, string $release_metadata, array $project_versions, array $expected_results, bool $allow_minor_updates = FALSE): void {
    $this->setCoreVersion($installed_version);
    $this->setReleaseMetadata(['drupal' => $release_metadata]);

    $this->config('automatic_updates.settings')
      ->set('allow_core_minor_updates', $allow_minor_updates)
      ->save();

    $stage = $this->container->get(UpdateStage::class);

    try {
      $stage->begin($project_versions);
      // Ensure that we did not, in fact, expect any errors.
      $this->assertEmpty($expected_results);
      // Reset the update stage for the next iteration of the loop.
      $stage->destroy();
    }
    catch (StageEventException $e) {
      $this->assertExpectedResultsFromException($expected_results, $e);
    }
  }

  /**
   * Creates an expected validation result from the version policy validator.
   *
   * Results returned from VersionPolicyValidator are always summarized in the
   * same way, so this method ensures that expected validation results are
   * summarized accordingly.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if it's not known.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   The error messages that the result should contain.
   *
   * @return \Drupal\package_manager\ValidationResult
   *   A validation error object with the appropriate summary.
   */
  private static function createVersionPolicyValidationResult(string $installed_version, ?string $target_version, array $messages): ValidationResult {
    if ($target_version) {
      $summary = t('Updating from Drupal @installed_version to @target_version is not allowed.', [
        '@installed_version' => $installed_version,
        '@target_version' => $target_version,
      ]);
    }
    else {
      $summary = t('Updating from Drupal @installed_version is not allowed.', [
        '@installed_version' => $installed_version,
      ]);
    }
    return ValidationResult::createError($messages, $summary);
  }

  /**
   * Tests that an error is raised if there are no stored package versions.
   *
   * This is a contrived situation that should never happen in real life, but
   * just in case it does, we need to be sure that it's an error condition.
   */
  public function testNoStagedPackageVersions(): void {
    // Remove the stored package versions from the update stage's metadata.
    $listener = function (PreCreateEvent $event): void {
      /** @var \Drupal\Tests\automatic_updates\Kernel\TestUpdateStage $stage */
      $stage = $event->stage;
      $stage->setMetadata('packages', [
        'production' => [],
      ]);
    };
    $this->assertTargetVersionNotDiscoverable($listener);
  }

  /**
   * Tests that an error is raised if no core packages are installed.
   *
   * This is a contrived situation that should never happen in real life, but
   * just in case it does, we need to be sure that it's an error condition.
   */
  public function testNoCorePackagesInstalled(): void {
    $listener = function (PreCreateEvent $event): void {
      // We should have staged package versions.
      /** @var \Drupal\automatic_updates\UpdateStage $stage */
      $stage = $event->stage;
      $this->assertNotEmpty($stage->getPackageVersions());
      // Remove all core packages in the active directory.
      (new ActiveFixtureManipulator())
        ->removePackage('drupal/core-recommended')
        ->removePackage('drupal/core')
        ->removePackage('drupal/core-dev', TRUE)
        ->commitChanges();
    };
    $this->assertTargetVersionNotDiscoverable($listener);
  }

  /**
   * Asserts that an error is raised if the target version of Drupal is unknown.
   *
   * @param \Closure $listener
   *   A pre-create event listener to run before all validators. This should put
   *   the test project and/or update stage into a state which will cause
   *   \Drupal\automatic_updates\Validator\VersionPolicyValidator::getTargetVersion()
   *   to throw an exception because the target version of Drupal core is not
   *   known.
   */
  private function assertTargetVersionNotDiscoverable(\Closure $listener): void {
    $this->addEventTestListener($listener, PreCreateEvent::class);

    $this->expectException(StageException::class);
    $this->expectExceptionMessage('The target version of Drupal core could not be determined.');
    $this->container->get(UpdateStage::class)
      ->begin(['drupal' => '9.8.1']);
  }

}
