<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Traits;

/**
 * Common methods for testing version policy rules.
 *
 * @internal
 */
trait VersionPolicyTestTrait {

  /**
   * Tests that a policy rule returns a set of errors.
   *
   * @param object $rule
   *   The policy rule under test.
   * @param string $installed_version
   *   The installed version of Drupal.
   * @param string|null $target_version
   *   The target version of Drupal, or NULL if it's not known.
   * @param string[] $expected_errors
   *   The expected error messages, if any.
   * @param \Drupal\update\ProjectRelease[] $available_releases
   *   (optional) The available releases of Drupal core, keyed by version.
   *   Defaults to an empty array.
   */
  protected function assertPolicyErrors(object $rule, string $installed_version, ?string $target_version, array $expected_errors, array $available_releases = []): void {
    $rule->setStringTranslation($this->getStringTranslationStub());

    $actual_errors = array_map('strval', $rule->validate($installed_version, $target_version, $available_releases));
    $this->assertSame($expected_errors, $actual_errors);
  }

}
