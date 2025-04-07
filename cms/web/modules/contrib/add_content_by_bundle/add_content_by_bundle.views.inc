<?php

/**
 * @file
 * Provide views data for our Add Content by Bundle module.
 */

/**
 * Implements hook_views_data().
 */
function add_content_by_bundle_views_data() {

  $data['views']['add_content_by_bundle'] = [
    'title' => t('Add Content by Bundle link'),
    'help' => t('Provide a link to add content for a specific content type.'),
    'area' => [
      'id' => 'add_content_by_bundle',
    ],
  ];

  return $data;
}
