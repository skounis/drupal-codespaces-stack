<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemap;

/**
 * The simple_sitemap.custom_link_manager service.
 */
class CustomLinkManager implements SitemapGetterInterface {

  use SitemapGetterTrait;
  use LinkSettingsTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Default link settings.
   *
   * @var array
   */
  protected static $linkSettingDefaults = [
    'priority' => '0.5',
    'changefreq' => '',
  ];

  /**
   * CustomLinkManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    PathValidatorInterface $path_validator,
  ) {
    $this->configFactory = $config_factory;
    $this->pathValidator = $path_validator;
  }

  /**
   * Stores a custom path along with its settings to configuration.
   *
   * Does so for the currently set variants.
   *
   * @param string $path
   *   The path to add.
   * @param array $settings
   *   Settings that are not provided are supplemented by defaults.
   *
   * @return $this
   *
   * @todo Validate $settings.
   */
  public function add(string $path, array $settings = []): CustomLinkManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }

    if (!$this->pathValidator->getUrlIfValidWithoutAccessCheck($path)) {
      throw new \InvalidArgumentException("The path '$path' must be local and known to Drupal.");
    }
    if ($path[0] !== '/') {
      throw new \InvalidArgumentException("The path '$path' must start with a '/'.");
    }

    $variant_links = $this->get();
    foreach ($variants as $variant) {
      $links = [];
      $link_key = 0;
      if (isset($variant_links[$variant])) {
        $links = $variant_links[$variant];
        $link_key = count($links);
        foreach ($links as $key => $link) {
          if ($link['path'] === $path) {
            $link_key = $key;
            break;
          }
        }
      }

      $links[$link_key] = ['path' => $path] + $settings;
      $this->configFactory->getEditable("simple_sitemap.custom_links.$variant")
        ->set('links', $links)->save();
    }

    return $this;
  }

  /**
   * Gets custom link settings for the currently set variants.
   *
   * @param string|null $path
   *   Limits the result set by an internal path.
   *
   * @return array
   *   An array of custom link settings keyed by variant name.
   */
  public function get(?string $path = NULL): array {
    $all_custom_links = [];
    foreach (array_keys($this->getSitemaps()) as $variant) {
      $custom_links = $this->configFactory
        ->get("simple_sitemap.custom_links.$variant")
        ->get('links');

      $custom_links = $custom_links ?: [];

      if ($custom_links && $path !== NULL) {
        foreach ($custom_links as $key => $link) {
          if ($link['path'] !== $path) {
            unset($custom_links[$key]);
          }
        }
      }

      foreach ($custom_links as $i => $link_settings) {
        self::supplementDefaultSettings($link_settings);
        $custom_links[$i] = $link_settings;
      }

      $custom_links = $path !== NULL && $custom_links
        ? array_values($custom_links)[0]
        : array_values($custom_links);

      $all_custom_links[$variant] = $custom_links;
    }

    return $all_custom_links;
  }

  /**
   * Removes custom links from currently set variants.
   *
   * @param array|string|null $paths
   *   Limits the removal to certain paths.
   *
   * @return $this
   */
  public function remove($paths = NULL): CustomLinkManager {
    if (empty($variants = array_keys($this->getSitemaps()))) {
      return $this;
    }

    if (NULL === $paths) {
      foreach ($variants as $variant) {
        $this->configFactory
          ->getEditable("simple_sitemap.custom_links.$variant")->delete();
      }
    }
    else {
      $variant_links = $this->get();
      foreach ($variant_links as $variant => $links) {
        $custom_links = $links;
        $save = FALSE;
        foreach ((array) $paths as $path) {
          foreach ($custom_links as $key => $link) {
            if ($link['path'] === $path) {
              unset($custom_links[$key]);
              $save = TRUE;
              break 2;
            }
          }
        }
        if ($save) {
          $this->configFactory->getEditable("simple_sitemap.custom_links.$variant")
            ->set('links', array_values($custom_links))->save();
        }
      }
    }

    return $this;
  }

  /**
   * Gets all compatible sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   Array of sitemaps of a type that implements a custom URL
   *   generator.
   */
  protected function getCompatibleSitemaps(): array {
    foreach (SimpleSitemap::loadMultiple() as $variant => $sitemap) {
      if ($sitemap->getType()->hasUrlGenerator('custom')) {
        $sitemaps[$variant] = $sitemap;
      }
    }

    return $sitemaps ?? [];
  }

}
