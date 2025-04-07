#!/usr/bin/env php
<?php

/**
 * @file
 * Provides a terminal command for performing automatic updates.
 */

use Drupal\automatic_updates\Commands\PostApplyCommand;
use Drupal\automatic_updates\Commands\RunCommand;
use Symfony\Component\Console\Application;

if (PHP_SAPI !== 'cli') {
  throw new \RuntimeException('This command must be run from the command line.');
}

// Find the autoloader. We know that Automatic Updates is installed somewhere
// in a Drupal code base, so move up the file system until we find
// `./vendor/autoload.php`.
$current_dir = __DIR__;
$previous_dir = NULL;
while ($current_dir !== $previous_dir) {
  $file = $current_dir . '/vendor/autoload.php';
  if (file_exists($file)) {
    /** @var \Composer\Autoload\ClassLoader $autoloader */
    $autoloader = require_once $file;
    break;
  }
  $previous_dir = $current_dir;
  $current_dir = dirname($current_dir);
}

if (empty($autoloader)) {
  throw new \RuntimeException('The autoloader could not be found. Did you run `composer install`?');
}

// Automatic Updates' namespace is not available for autoloading because it is
// a Drupal module, which means Drupal must be booted up in order to access it.
// Since Drupal isn't booted yet, we need to make the autoloader aware of the
// command namespace.
$autoloader->addPsr4('Drupal\\automatic_updates\\Commands\\', __DIR__ . '/src/Commands');

$application = new Application('Automatic Updates', '3.0.0');
$application->add(new RunCommand($autoloader));
$application->add(new PostApplyCommand($autoloader));
$application->setDefaultCommand('run')->run();
