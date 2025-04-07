<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface;

/**
 * Defines the simple_sitemap entity.
 *
 * @ConfigEntityType(
 *   id = "simple_sitemap_type",
 *   label = @Translation("Simple XML sitemap type"),
 *   label_collection = @Translation("Sitemap types"),
 *   label_singular = @Translation("sitemap type"),
 *   label_plural = @Translation("sitemap types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count sitemap type",
 *     plural = "@count sitemap types",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage",
 *     "list_builder" = "\Drupal\simple_sitemap\SimpleSitemapTypeListBuilder",
 *     "form" = {
 *       "default" = "\Drupal\simple_sitemap\Form\SimpleSitemapTypeEntityForm",
 *       "add" = "\Drupal\simple_sitemap\Form\SimpleSitemapTypeEntityForm",
 *       "edit" = "\Drupal\simple_sitemap\Form\SimpleSitemapTypeEntityForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *   },
 *   config_prefix = "type",
 *   admin_permission = "administer sitemap settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "sitemap_generator",
 *     "url_generators",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/search/simplesitemap/types/add",
 *     "edit-form" = "/admin/config/search/simplesitemap/types/{simple_sitemap_type}",
 *     "delete-form" = "/admin/config/search/simplesitemap/types/{simple_sitemap_type}/delete",
 *     "collection" = "/admin/config/search/simplesitemap/types",
 *   },
 * )
 */
class SimpleSitemapType extends ConfigEntityBase implements SimpleSitemapTypeInterface {

  /**
   * The sitemap generator.
   *
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface|null
   */
  protected $sitemapGenerator;

  /**
   * The URL generators.
   *
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorInterface[]|null
   */
  protected $urlGenerators;

  /**
   * {@inheritdoc}
   */
  public function getSitemapGenerator(): SitemapGeneratorInterface {
    if ($this->sitemapGenerator === NULL) {
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.simple_sitemap.sitemap_generator');
      $this->sitemapGenerator = $manager
        ->createInstance($this->get('sitemap_generator'));
    }

    return $this->sitemapGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlGenerators(): array {
    if ($this->urlGenerators === NULL) {
      $this->urlGenerators = [];
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.simple_sitemap.url_generator');
      foreach ($this->get('url_generators') as $generator_id) {
        $this->urlGenerators[$generator_id] = $manager->createInstance($generator_id);
      }
    }

    return $this->urlGenerators;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUrlGenerator(string $generator_id): bool {
    return in_array($generator_id, $this->get('url_generators'), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    if ($property_name === 'sitemap_generator') {
      $this->sitemapGenerator = NULL;
    }
    elseif ($property_name === 'url_generators') {
      $this->urlGenerators = NULL;
    }

    return parent::set($property_name, $value);
  }

}
