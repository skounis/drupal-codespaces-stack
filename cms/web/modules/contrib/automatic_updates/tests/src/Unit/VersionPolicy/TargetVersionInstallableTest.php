<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionInstallable;
use Drupal\update\ProjectRelease;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\TargetVersionInstallable
 * @group automatic_updates
 * @internal
 */
class TargetVersionInstallableTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testTargetVersionInstallable().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerTargetVersionInstallable(): array {
    return [
      'no available releases' => [
        [TRUE, FALSE],
        '9.8.1',
        '9.8.2',
        [],
        ['Cannot update Drupal core to 9.8.2 because it is not in the list of installable releases.'],
      ],
      'unknown target' => [
        [TRUE, FALSE],
        '9.8.1',
        '9.8.2',
        [
          '9.8.1' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-8-1-release',
            'version' => '9.8.1',
          ]),
        ],
        ['Cannot update Drupal core to 9.8.2 because it is not in the list of installable releases.'],
      ],
      'valid target' => [
        [TRUE, FALSE],
        '9.8.1',
        '9.8.2',
        [
          '9.8.2' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-8-2-release',
            'version' => '9.8.2',
          ]),
        ],
        [],
      ],
      'installed version and target version are the same' => [
        [TRUE, FALSE],
        '9.8.0',
        '9.8.0',
        [],
        ['Cannot update Drupal core to 9.8.0 because it is not in the list of installable releases.'],
      ],
      'unknown patch update' => [
        [TRUE, FALSE],
        '9.8.0',
        '9.8.2',
        [],
        ['Cannot update Drupal core to 9.8.2 because it is not in the list of installable releases.'],
      ],
      'valid target version newer in same minor' => [
        [TRUE, FALSE],
        '9.8.0',
        '9.8.2',
        [
          '9.8.2' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-8-2-release',
            'version' => '9.8.2',
          ]),
        ],
        [],
      ],
      'target version in newer minor, minor updates forbidden' => [
        [FALSE],
        '9.8.0',
        '9.9.2',
        [],
        ['Drupal cannot be automatically updated from 9.8.0 to 9.9.2 because automatic updates from one minor version to another are not supported.'],
      ],
      'unknown target version in newer minor, minor updates allowed' => [
        [TRUE],
        '9.8.0',
        '9.9.2',
        [],
        ['Cannot update Drupal core to 9.9.2 because it is not in the list of installable releases.'],
      ],
      'valid target version in newer minor, minor updates allowed' => [
        [TRUE],
        '9.8.0',
        '9.9.2',
        [
          '9.9.2' => ProjectRelease::createFromArray([
            'status' => 'published',
            'release_link' => 'http://example.com/drupal-9-9-2-release',
            'version' => '9.9.2',
          ]),
        ],
        [],
      ],
      'target version older in same minor' => [
        [TRUE, FALSE],
        '9.8.2',
        '9.8.0',
        [],
        ['Cannot update Drupal core to 9.8.0 because it is not in the list of installable releases.'],
      ],
      'target version in older minor, minor updates forbidden' => [
        [FALSE],
        '9.8.0',
        '9.7.2',
        [],
        ['Drupal cannot be automatically updated from 9.8.0 to 9.7.2 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in older minor, minor updates allowed' => [
        [TRUE],
        '9.8.0',
        '9.7.2',
        [],
        ['Cannot update Drupal core to 9.7.2 because it is not in the list of installable releases.'],
      ],
      // In practice, the message produced by the next four cases will be
      // superseded by the MajorVersionMatch rule.
      // @see \Drupal\automatic_updates\Validator\VersionPolicy\MajorVersionMatch
      // @see \Drupal\automatic_updates\Validator\VersionPolicyValidator::isRuleSuperseded()
      'target version in older major, minor updates forbidden' => [
        [FALSE],
        '9.8.0',
        '8.8.0',
        [],
        ['Drupal cannot be automatically updated from 9.8.0 to 8.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in older major, minor updates allowed' => [
        [TRUE],
        '9.8.0',
        '8.8.0',
        [],
        ['Cannot update Drupal core to 8.8.0 because it is not in the list of installable releases.'],
      ],
      'target version in newer major, minor updates forbidden' => [
        [FALSE],
        '9.8.0',
        '10.8.0',
        [],
        ['Drupal cannot be automatically updated from 9.8.0 to 10.8.0 because automatic updates from one minor version to another are not supported.'],
      ],
      'target version in newer major, minor updates allowed' => [
        [TRUE],
        '9.8.0',
        '10.8.0',
        [],
        ['Cannot update Drupal core to 10.8.0 because it is not in the list of installable releases.'],
      ],
    ];
  }

  /**
   * Tests that the target version must be a known, installable release.
   *
   * @param bool[] $minor_updates_allowed
   *   The values of the allow_core_minor_updates config flag. The rule will be
   *   tested separately with each value.
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string $target_version
   *   The target version of Drupal core, or NULL if not known.
   * @param \Drupal\update\ProjectRelease[] $available_releases
   *   The available releases of Drupal core, keyed by version.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerTargetVersionInstallable
   */
  public function testTargetVersionInstallable(array $minor_updates_allowed, string $installed_version, string $target_version, array $available_releases, array $expected_errors): void {
    foreach ($minor_updates_allowed as $value) {
      $config_factory = $this->getConfigFactoryStub([
        'automatic_updates.settings' => [
          'allow_core_minor_updates' => $value,
        ],
      ]);
      $rule = new TargetVersionInstallable($config_factory);
      $this->assertPolicyErrors($rule, $installed_version, $target_version, $expected_errors, $available_releases);
    }
  }

}
