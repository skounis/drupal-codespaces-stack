<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Traits;

use Behat\Mink\WebAssert;
use Drupal\Tests\BrowserTestBase;

/**
 * Common methods for testing the update form.
 *
 * @internal
 *   This class is an internal part of the module's testing infrastructure and
 *   should not be used by external code.
 */
trait FormTestTrait {

  /**
   * Asserts the table shows the updates.
   *
   * @param \Behat\Mink\WebAssert $assert
   *   The web assert tool.
   * @param string $expected_project_title
   *   The expected project title.
   * @param string $expected_installed_version
   *   The expected installed version.
   * @param string $expected_target_version
   *   The expected target version.
   * @param int $row
   *   The row number.
   */
  private function assertUpdateTableRow(WebAssert $assert, string $expected_project_title, string $expected_installed_version, string $expected_target_version, int $row = 1): void {
    $row_selector = ".update-recommended tr:nth-of-type($row)";
    $assert->elementTextContains('css', $row_selector . ' td:nth-of-type(2)', $expected_project_title);
    $assert->elementTextContains('css', $row_selector . ' td:nth-of-type(3)', $expected_installed_version);
    $target_selector = $row_selector . ' td:nth-of-type(4)';
    $cell = $assert->elementExists('css', $target_selector);
    $link_url = $assert->elementExists('named', ['link', 'Release notes'], $cell)->getAttribute('href');
    $this->assertStringContainsString(str_replace('.', '-', $expected_target_version) . '-release', $link_url);
  }

  /**
   * Asserts the table shows the expected number of updates.
   *
   * @param int $expected_update_count
   *   The no of rows in table.
   */
  protected function assertUpdatesCount(int $expected_update_count): void {
    assert($this instanceof BrowserTestBase);
    $this->assertSession()->elementsCount('css', '.update-recommended tbody tr', $expected_update_count);
  }

}
