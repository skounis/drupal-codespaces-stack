<?php

/**
 * @file
 * Allows for specific PHPStan ignores for different versions of Drupal core.
 */

declare(strict_types = 1);

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Drupal\Core\Recipe\Recipe;

$includes = [];
if (method_exists(Recipe::class, 'getExtra')) {
  if (InstalledVersions::satisfies(new VersionParser(), 'phpstan/phpstan', '^2')) {
    $includes[] = 'phpstan-baseline-getExtras.neon';
  }
}
else {
  $includes[] = 'phpstan-baseline-no-getExtras.neon';
}

$config = [];
$config['includes'] = $includes;
return $config;
