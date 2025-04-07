<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\ForbidDevSnapshot;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\ForbidDevSnapshot
 * @group automatic_updates
 * @internal
 */
class ForbidDevSnapshotTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testForbidDevSnapshot().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerForbidDevSnapshot(): array {
    return [
      'stable version installed' => [
        '9.8.0',
        [],
      ],
      'alpha version installed' => [
        '9.8.0-alpha3',
        [],
      ],
      'beta version installed' => [
        '9.8.0-beta7',
        [],
      ],
      'release candidate installed' => [
        '9.8.0-rc2',
        [],
      ],
      'dev snapshot installed' => [
        '9.8.0-dev',
        ['Drupal cannot be automatically updated from the installed version, 9.8.0-dev, because automatic updates from a dev version to any other version are not supported.'],
      ],
    ];
  }

  /**
   * Tests that trying to update from a dev snapshot raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerForbidDevSnapshot
   */
  public function testForbidDevSnapshot(string $installed_version, array $expected_errors): void {
    $rule = new ForbidDevSnapshot();
    $this->assertPolicyErrors($rule, $installed_version, '9.8.1', $expected_errors);
  }

}
