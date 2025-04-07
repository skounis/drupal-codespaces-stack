<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\MajorVersionMatch;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\MajorVersionMatch
 * @group automatic_updates
 * @internal
 */
class MajorVersionMatchTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testMajorVersionMatch().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerMajorVersionMatch(): array {
    return [
      'same versions' => [
        '9.8.0',
        '9.8.0',
        [],
      ],
      'target version newer in same minor' => [
        '9.8.0',
        '9.8.2',
        [],
      ],
      'target version in newer minor' => [
        '9.8.0',
        '9.9.2',
        [],
      ],
      'target version older in same minor' => [
        '9.8.2',
        '9.8.0',
        [],
      ],
      'target version in older minor' => [
        '9.8.0',
        '9.7.2',
        [],
      ],
      'target version in newer major' => [
        '9.8.0',
        '10.0.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 10.0.0 because automatic updates from one major version to another are not supported.'],
      ],
      'target version in older major' => [
        '9.8.0',
        '8.9.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 8.9.0 because automatic updates from one major version to another are not supported.'],
      ],
    ];
  }

  /**
   * Tests that trying to update across major versions raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if not known.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerMajorVersionMatch
   */
  public function testMajorVersionMatch(string $installed_version, ?string $target_version, array $expected_errors): void {
    $rule = new MajorVersionMatch();
    $this->assertPolicyErrors($rule, $installed_version, $target_version, $expected_errors);
  }

}
