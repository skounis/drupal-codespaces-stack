<?php

/**
 * @file
 * Post update functions for the Simple XML Sitemap module.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Prevent config:import from deleting and recreating configs created through update hooks.
 *
 * @see https://www.drupal.org/project/simple_sitemap/issues/3236623
 */
function simple_sitemap_post_update_8403(&$sandbox) {
  $config_factory = \Drupal::configFactory();
  $settings = Drupal::service('settings');

  foreach (['simple_sitemap.sitemap.', 'simple_sitemap.type.'] as $config_prefix) {
    foreach ($config_factory->listAll($config_prefix) as $config) {
      $data = Yaml::parse(@file_get_contents($settings->get('config_sync_directory') . '/' . $config . '.yml'));
      if ($data && $data['uuid']) {
        $config_factory->getEditable($config)->set('uuid', $data['uuid'])->save(TRUE);
      }
    }
  }
}

/**
 * Clear cache as service definitions changed.
 *
 * @see https://www.drupal.org/project/simple_sitemap/issues/3444946
 */
function simple_sitemap_post_update_8404(?array &$sandbox = NULL): void {
}
