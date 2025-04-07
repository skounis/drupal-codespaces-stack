<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Unit;

use Drupal\package_manager\LegacyVersionUtility;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\package_manager\LegacyVersionUtility
 * @group automatic_updates
 * @internal
 */
class LegacyVersionUtilityTest extends UnitTestCase {

  /**
   * @covers ::convertToSemanticVersion
   *
   * @param string $version_number
   *   The version number to covert.
   * @param string $expected
   *   The expected result.
   *
   * @dataProvider providerConvertToSemanticVersion
   */
  public function testConvertToSemanticVersion(string $version_number, string $expected): void {
    $this->assertSame($expected, LegacyVersionUtility::convertToSemanticVersion($version_number));
  }

  /**
   * Data provider for testConvertToSemanticVersion()
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerConvertToSemanticVersion(): array {
    return [
      '8.x-1.2' => ['8.x-1.2', '1.2.0'],
      '8.x-1.2-alpha1' => ['8.x-1.2-alpha1', '1.2.0-alpha1'],
      '1.2.0' => ['1.2.0', '1.2.0'],
      '1.2.0-alpha1' => ['1.2.0-alpha1', '1.2.0-alpha1'],
    ];
  }

  /**
   * @covers ::convertToLegacyVersion
   *
   * @param string $version_number
   *   The version number to covert.
   * @param string|null $expected
   *   The expected result.
   *
   * @dataProvider providerConvertToLegacyVersion
   */
  public function testConvertToLegacyVersion(string $version_number, ?string $expected): void {
    $this->assertSame($expected, LegacyVersionUtility::convertToLegacyVersion($version_number));
  }

  /**
   * Data provider for testConvertToLegacyVersion()
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerConvertToLegacyVersion(): array {
    return [
      '1.2.0' => ['1.2.0', '8.x-1.2'],
      '1.2.0-alpha1' => ['1.2.0-alpha1', '8.x-1.2-alpha1'],
      '8.x-1.2' => ['8.x-1.2', '8.x-1.2'],
      '8.x-1.2-alpha1' => ['8.x-1.2-alpha1', '8.x-1.2-alpha1'],
      '1.2.3' => ['1.2.3', NULL],
      '1.2.3-alpha1' => ['1.2.3-alpha1', NULL],
    ];
  }

}
