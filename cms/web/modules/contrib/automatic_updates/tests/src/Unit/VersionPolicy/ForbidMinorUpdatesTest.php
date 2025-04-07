<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit\VersionPolicy;

use Drupal\automatic_updates\Validator\VersionPolicy\ForbidMinorUpdates;
use Drupal\Tests\automatic_updates\Traits\VersionPolicyTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @covers \Drupal\automatic_updates\Validator\VersionPolicy\ForbidMinorUpdates
 * @group automatic_updates
 * @internal
 */
class ForbidMinorUpdatesTest extends UnitTestCase {

  use VersionPolicyTestTrait;

  /**
   * Data provider for testMinorUpdateForbidden().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerMinorUpdateForbidden(): array {
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
      'target version older in same minor' => [
        '9.8.2',
        '9.8.0',
        [],
      ],
      'target version in older minor' => [
        '9.8.0',
        '9.7.2',
        ['Drupal cannot be automatically updated from 9.8.0 to 9.7.2 because automatic updates from one minor version to another are not supported during cron.'],
      ],

      'target version in newer minor' => [
        '9.8.0',
        '9.9.2',
        ['Drupal cannot be automatically updated from 9.8.0 to 9.9.2 because automatic updates from one minor version to another are not supported during cron.'],
      ],
      'target version in older major' => [
        '9.8.0',
        '8.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 8.8.0 because automatic updates from one minor version to another are not supported during cron.'],
      ],
      'target version in newer major' => [
        '9.8.0',
        '10.8.0',
        ['Drupal cannot be automatically updated from 9.8.0 to 10.8.0 because automatic updates from one minor version to another are not supported during cron.'],
      ],
      'target version in older major and minor' => [
        '9.8.0',
        '8.9.9',
        ['Drupal cannot be automatically updated from 9.8.0 to 8.9.9 because automatic updates from one minor version to another are not supported during cron.'],
      ],
      'target version in newer major and minor' => [
        '9.8.0',
        '10.9.2',
        ['Drupal cannot be automatically updated from 9.8.0 to 10.9.2 because automatic updates from one minor version to another are not supported during cron.'],
      ],
    ];
  }

  /**
   * Tests that trying to update across minor versions raises an error.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param string|null $target_version
   *   The target version of Drupal core, or NULL if not known.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   *
   * @dataProvider providerMinorUpdateForbidden
   */
  public function testMinorUpdateForbidden(string $installed_version, ?string $target_version, array $expected_errors): void {
    $rule = new ForbidMinorUpdates();
    $this->assertPolicyErrors($rule, $installed_version, $target_version, $expected_errors);
  }

}
