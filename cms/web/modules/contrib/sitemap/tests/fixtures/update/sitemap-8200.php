<?php

/**
 * @file
 * Contains database additions for Sitemap schema version 8200.
 */

// cspell:disable
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$extensions['module']['sitemap'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'sitemap.settings',
    'data' => 'a:5:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"GUKBlXIBQ2d0H8Q7M_Rqn1Q93tRxLPfs9cG-VeQC9NI";}s:10:"page_title";s:7:"Sitemap";s:7:"message";a:2:{s:5:"value";s:0:"";s:6:"format";s:10:"plain_text";}s:7:"plugins";a:3:{s:15:"vocabulary:tags";a:5:{s:7:"enabled";b:1;s:6:"weight";i:0;s:8:"settings";a:12:{s:5:"title";s:4:"Tags";s:16:"show_description";b:0;s:10:"show_count";b:0;s:19:"display_unpublished";b:0;s:10:"term_depth";i:9;s:20:"term_count_threshold";i:0;s:14:"customize_link";b:0;s:9:"term_link";s:44:"entity.taxonomy_term.canonical|taxonomy_term";s:11:"always_link";b:0;s:10:"enable_rss";b:0;s:8:"rss_link";s:31:"view.taxonomy_term.feed_1|arg_0";s:9:"rss_depth";i:9;}s:2:"id";s:15:"vocabulary:tags";s:8:"provider";s:7:"sitemap";}s:9:"frontpage";a:5:{s:7:"enabled";b:1;s:6:"weight";i:0;s:8:"settings";a:2:{s:5:"title";s:10:"Front page";s:3:"rss";s:8:"/rss.xml";}s:2:"id";s:9:"frontpage";s:8:"provider";s:7:"sitemap";}s:9:"menu:main";a:5:{s:7:"enabled";b:1;s:6:"weight";i:0;s:8:"settings";a:2:{s:5:"title";s:15:"Main navigation";s:13:"show_disabled";b:0;}s:2:"id";s:9:"menu:main";s:8:"provider";s:7:"sitemap";}}s:11:"include_css";b:1;}',
  ])
  ->execute();

$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'system.schema',
    'name' => 'sitemap',
    'value' => 'i:8200;',
  ])
  ->execute();

$connection->insert('router')
  ->fields([
    'name',
    'path',
    'pattern_outline',
    'fit',
    'route',
    'number_parts',
  ])
  ->values([
    'name' => 'sitemap.page',
    'path' => '/sitemap',
    'pattern_outline' => '/sitemap',
    'fit' => '1',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:8:"/sitemap";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:11:"_controller";s:58:"\Drupal\sitemap\Controller\SitemapController::buildSitemap";s:15:"_title_callback";s:54:"\Drupal\sitemap\Controller\SitemapController::getTitle";}s:12:"requirements";a:1:{s:11:"_permission";s:14:"access sitemap";}s:7:"options";a:3:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:15:"{^/sitemap$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:8:"/sitemap";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:1;s:14:"patternOutline";s:8:"/sitemap";s:8:"numParts";i:1;}}',
    'number_parts' => '1',
  ])
  ->values([
    'name' => 'sitemap.settings',
    'path' => '/admin/config/search/sitemap',
    'pattern_outline' => '/admin/config/search/sitemap',
    'fit' => '15',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:28:"/admin/config/search/sitemap";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:5:"_form";s:40:"\Drupal\sitemap\Form\SitemapSettingsForm";s:6:"_title";s:7:"Sitemap";}s:12:"requirements";a:1:{s:11:"_permission";s:18:"administer sitemap";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:4:"utf8";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:35:"{^/admin/config/search/sitemap$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:28:"/admin/config/search/sitemap";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:15;s:14:"patternOutline";s:28:"/admin/config/search/sitemap";s:8:"numParts";i:4;}}',
    'number_parts' => '4',
  ])
  ->execute();
