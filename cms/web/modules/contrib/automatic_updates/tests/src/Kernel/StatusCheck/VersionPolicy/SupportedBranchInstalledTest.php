<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\SupportedBranchInstalled;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\SupportedBranchInstalled
 * @group automatic_updates
 * @internal
 */
class SupportedBranchInstalledTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Data provider for testSupportedBranchInstalled().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerSupportedBranchInstalled(): array {
    return [
      'supported minor installed' => [
        '9.8.0',
        [FALSE, TRUE],
        [],
      ],
      // These two cases test a supported major version, but unsupported minor
      // version.
      'supported major installed, minor updates forbidden' => [
        '9.6.1',
        [FALSE],
        [
          'The currently installed version of Drupal core, 9.6.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.',
          'See the <a href="/admin/reports/updates">available updates page</a> for available updates.',
        ],
      ],
      'supported major installed, minor updates allowed' => [
        '9.6.1',
        [TRUE],
        [
          'The currently installed version of Drupal core, 9.6.1, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.',
          'Use the <a href="/admin/modules/update">update form</a> to update to a supported version.',
        ],
      ],
      'unsupported version installed' => [
        '8.9.0',
        [FALSE, TRUE],
        [
          'The currently installed version of Drupal core, 8.9.0, is not in a supported minor version. Your site will not be automatically updated during cron until it is updated to a supported minor version.',
          'See the <a href="/admin/reports/updates">available updates page</a> for available updates.',
        ],
      ],
    ];
  }

  /**
   * Tests that the installed version of Drupal must be in a supported branch.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param bool[] $allow_minor_updates
   *   The values of the `allow_core_minor_updates` config setting that should
   *   be tested.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerSupportedBranchInstalled
   */
  public function testSupportedBranchInstalled(string $installed_version, array $allow_minor_updates, array $expected_errors): void {
    $this->setCoreVersion($installed_version);
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../../package_manager/tests/fixtures/release-history/drupal.9.8.2.xml',
    ]);

    $rule = SupportedBranchInstalled::create($this->container);

    foreach ($allow_minor_updates as $setting) {
      $this->config('automatic_updates.settings')
        ->set('allow_core_minor_updates', $setting)
        ->save();

      $actual_errors = array_map('strval', $rule->validate($installed_version));
      $this->assertSame($expected_errors, $actual_errors);
    }
  }

}
