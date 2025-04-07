<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\ForbidDowngrade;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\ForbidDowngrade
 * @group automatic_updates
 * @internal
 */
class ForbidDowngradeTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testDowngradeForbidden().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerDowngradeForbidden(): array {
    return [
      'unknown target version' => [
        '9.8.0',
        NULL,
        ['Update version  is lower than 9.8.0, downgrading is not supported.'],
      ],
      'same versions' => [
        '9.8.0',
        '9.8.0',
        [],
      ],
      'newer target version' => [
        '9.8.0',
        '9.8.2',
        [],
      ],
      'older target version' => [
        '9.8.2',
        '9.8.0',
        ['Update version 9.8.0 is lower than 9.8.2, downgrading is not supported.'],
      ],
    ];
  }

  /**
   * Tests that downgrading always raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if not known.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerDowngradeForbidden
   */
  public function testDowngradeForbidden(string $installed_version, ?string $target_version, array $expected_errors): void {
    $rule = new ForbidDowngrade();
    $this->assertPolicyErrors($rule, $installed_version, $target_version, $expected_errors);
  }

}
