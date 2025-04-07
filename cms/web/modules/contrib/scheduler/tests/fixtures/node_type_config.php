<?php

/**
 * @file
 * DB fixture for scheduler node type migration tests on top of core's fixture.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('system')
  ->fields([
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
    'info',
  ])
  ->values([
    'filename' => 'sites/all/modules/contrib/scheduler/scheduler.module',
    'name' => 'scheduler',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7103',
    'weight' => '0',
    'info' => 'a:14:{s:4:\"name\";s:9:\"Scheduler\";s:11:\"description\";s:85:\"This module allows nodes to be published and unpublished on specified dates and time.\";s:4:\"core\";s:3:\"7.x\";s:9:\"configure\";s:30:\"admin/config/content/scheduler\";s:5:\"files\";a:3:{i:0;s:47:\"scheduler_handler_field_scheduler_countdown.inc\";i:1;s:20:\"tests/scheduler.test\";i:2;s:24:\"tests/scheduler_api.test\";}s:17:\"test_dependencies\";a:2:{i:0;s:4:\"date\";i:1;s:5:\"rules\";}s:7:\"version\";s:7:\"7.x-1.6\";s:7:\"project\";s:9:\"scheduler\";s:9:\"datestamp\";s:10:\"1600171819\";s:5:\"mtime\";i:1600171819;s:12:\"dependencies\";a:0:{}s:7:\"package\";s:5:\"Other\";s:3:\"php\";s:5:\"5.2.4\";s:9:\"bootstrap\";i:0;}',
  ])
  ->execute();

$connection->insert('variable')
  ->fields([
    'name',
    'value',
  ])
  ->values([
    'name' => 'scheduler_expand_fieldset_article',
    'value' => 's:1:"0";',
  ])
  ->values([
    'name' => 'scheduler_expand_fieldset_page',
    'value' => 's:1:"1";',
  ])
  ->values([
    'name' => 'scheduler_publish_enable_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_publish_enable_page',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_publish_past_date_article',
    'value' => 's:5:"error";',
  ])
  ->values([
    'name' => 'scheduler_publish_past_date_page',
    'value' => 's:7:"publish";',
  ])
  ->values([
    'name' => 'scheduler_publish_required_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_publish_required_page',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_publish_revision_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_publish_revision_page',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_publish_touch_article',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_publish_touch_page',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_enable_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_enable_page',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_required_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_required_page',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_revision_article',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'scheduler_unpublish_revision_page',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'scheduler_use_vertical_tabs_article',
    'value' => 's:1:"1";',
  ])
  ->values([
    'name' => 'scheduler_use_vertical_tabs_page',
    'value' => 's:1:"0";',
  ])
  ->execute();
