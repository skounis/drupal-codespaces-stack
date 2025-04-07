<?php

namespace Drupal\sitemap\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\sitemap\SitemapManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for update routes.
 */
class SitemapController implements ContainerInjectionInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The SitemapMap plugin manager.
   *
   * @var \Drupal\sitemap\SitemapManager
   */
  protected SitemapManager $sitemapManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->configFactory = $container->get('config.factory');
    $instance->sitemapManager = $container->get('plugin.manager.sitemap');
    return $instance;
  }

  /**
   * Controller for /sitemap.
   *
   * @return array
   *   Renderable array.
   */
  public function buildSitemap() {
    $config = $this->configFactory->get('sitemap.settings');

    // Build the Sitemap message.
    $message = '';
    if (!empty($config->get('message')) && !empty($config->get('message')['value'])) {
      $message = check_markup($config->get('message')['value'], $config->get('message')['format']);
    }

    // Build the plugin content.
    $plugins_config = $config->get('plugins');
    $plugins = [];
    foreach ($plugins_config as $id => $plugin_config) {
      if (!$this->sitemapManager->hasDefinition($id)) {
        continue;
      }

      $instance = $this->sitemapManager->createInstance($id, $plugin_config);
      if ($instance->enabled) {
        $plugins[] = $instance->view() + ['#weight' => $instance->weight];
      }
    }
    uasort($plugins, ['Drupal\Component\Utility\SortArray',
      'sortByWeightProperty',
    ]);

    // Build the render array.
    $sitemap = [
      '#theme' => 'sitemap',
      '#message' => $message,
      '#sitemap_items' => $plugins,
    ];

    // Check whether to include the default CSS.
    if ($config->get('include_css') == 1) {
      $sitemap['#attached']['library'] = [
        'sitemap/sitemap.theme',
      ];
    }

    $metadata = new CacheableMetadata();
    $metadata->addCacheableDependency($config);
    $metadata->applyTo($sitemap);

    return $sitemap;
  }

  /**
   * Returns sitemap page's title.
   *
   * @return string
   *   Sitemap page title.
   */
  public function getTitle() {
    $config = $this->configFactory->get('sitemap.settings');
    return $config->get('page_title');
  }

}
