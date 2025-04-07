<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\StableReleaseInstalled;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\StableReleaseInstalled
 * @group automatic_updates
 * @internal
 */
class StableReleaseInstalledTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testStableReleaseInstalled().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerStableReleaseInstalled(): array {
    return [
      'stable version installed' => [
        '9.8.0',
        [],
      ],
      'alpha version installed' => [
        '9.8.0-alpha3',
        ['Drupal cannot be automatically updated during cron from its current version, 9.8.0-alpha3, because it is not a stable version.'],
      ],
      'beta version installed' => [
        '9.8.0-beta7',
        ['Drupal cannot be automatically updated during cron from its current version, 9.8.0-beta7, because it is not a stable version.'],
      ],
      'release candidate installed' => [
        '9.8.0-rc2',
        ['Drupal cannot be automatically updated during cron from its current version, 9.8.0-rc2, because it is not a stable version.'],
      ],
    ];
  }

  /**
   * Tests that trying to update from a non-stable release raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerStableReleaseInstalled
   */
  public function testStableReleaseInstalled(string $installed_version, array $expected_errors): void {
    $rule = new StableReleaseInstalled();
    $this->assertPolicyErrors($rule, $installed_version, '9.8.1', $expected_errors);
  }

}
