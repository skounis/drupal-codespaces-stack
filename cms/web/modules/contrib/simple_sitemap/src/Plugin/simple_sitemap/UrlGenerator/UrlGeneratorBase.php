<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for UrlGenerator plugins.
 */
abstract class UrlGeneratorBase extends SimpleSitemapPluginBase implements UrlGeneratorInterface {

  /**
   * Simple XML Sitemap logger.
   *
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * The sitemap entity.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface
   */
  protected $sitemap;

  /**
   * UrlGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   Simple XML Sitemap logger.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Logger $logger,
    Settings $settings,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setSitemap(SimpleSitemapInterface $sitemap): UrlGeneratorInterface {
    $this->sitemap = $sitemap;

    return $this;
  }

  /**
   * Replaces the base URL with custom URL from settings.
   *
   * @param string $url
   *   URL to process.
   *
   * @return string
   *   The processed URL.
   */
  protected function replaceBaseUrlWithCustom(string $url): string {
    return !empty($base_url = $this->settings->get('base_url'))
      ? str_replace($GLOBALS['base_url'], $base_url, $url)
      : $url;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getDataSets(): array;

  /**
   * Processes the specified dataset.
   *
   * @param mixed $data_set
   *   Dataset to process.
   *
   * @return array
   *   Processing result.
   */
  abstract protected function processDataSet($data_set): array;

  /**
   * {@inheritdoc}
   */
  public function generate($data_set): array {
    try {
      return [$this->processDataSet($data_set)];
    }
    catch (SkipElementException $e) {
      return [];
    }
  }

  /**
   * Constructs the path data.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to process.
   * @param array $settings
   *   The sitemap settings.
   *
   * @return array
   *   The path data.
   */
  protected function constructPathData(Url $url, array $settings = []): array {
    $url = $url->setAbsolute();

    if ($url->isRouted()) {
      // Do not include paths that have no URL.
      if (in_array($url->getRouteName(), ['<nolink>', '<none>'])) {
        throw new SkipElementException();
      }

      $path = $url->getInternalPath();
    }
    // There can be internal paths that are not rooted, like 'base:/path'.
    elseif (str_starts_with($uri = $url->toUriString(), 'base:/')) {
      // Handle base scheme.
      $path = $uri[6] === '/' ? substr($uri, 7) : substr($uri, 6);
    }
    else {
      // Handle unforeseen schemes.
      $path = $uri;
    }

    $path_data = [
      'url' => $url->toString(),
      'lastmod' => $settings['lastmod'] ?? NULL,
      'priority' => $settings['priority'] ?? NULL,
      'changefreq' => !empty($settings['changefreq']) ? $settings['changefreq'] : NULL,
      'images' => $settings['images'] ?? NULL,
    ];

    // Additional info useful in hooks.
    $path_data['meta']['path'] = $path;
    if (($query = $url->getOption('query')) && is_array($query)) {
      $path_data['meta']['query'] = UrlHelper::buildQuery($query);
    }

    return $path_data;
  }

}
