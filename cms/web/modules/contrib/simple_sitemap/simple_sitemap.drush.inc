<?php

/**
 * @file
 * Drush (< 9) integration.
 *
 * @todo Remove integration as Drush < 9 is not supported anymore.
 */

use Drupal\simple_sitemap\Queue\QueueWorker;

/**
 * Implements hook_drush_command().
 */
function simple_sitemap_drush_command() {
  $items['simple-sitemap-generate'] = [
    'description' => 'Regenerate all sitemaps or continue generation.',
    'callback' => 'drush_simple_sitemap_generate',
    'drupal dependencies' => ['simple_sitemap'],
    'aliases' => ['ssg'],
  ];

  $items['simple-sitemap-rebuild-queue'] = [
    'description' => 'Queue all sitemaps for regeneration.',
    'callback' => 'drush_simple_sitemap_rebuild_queue',
    'drupal dependencies' => ['simple_sitemap'],
    'aliases' => ['ssr'],
  ];

  return $items;
}

/**
 * Callback function for hook_drush_command().
 *
 * Regenerate the XML sitemaps according to the module settings.
 */
function drush_simple_sitemap_generate() {
  /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
  $generator = \Drupal::service('simple_sitemap.generator');
  $generator->generate(QueueWorker::GENERATE_TYPE_DRUSH);
}

/**
 * Callback function for hook_drush_command().
 *
 * Rebuild the sitemap queue for all sitemaps.
 */
function drush_simple_sitemap_rebuild_queue() {
  /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
  $generator = \Drupal::service('simple_sitemap.generator');
  $generator->rebuildQueue();
}
