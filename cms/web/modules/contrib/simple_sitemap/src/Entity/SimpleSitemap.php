<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Exception\SitemapNotExistsException;

/**
 * Defines the simple_sitemap entity.
 *
 * @ConfigEntityType(
 *   id = "simple_sitemap",
 *   label = @Translation("Sitemap"),
 *   label_collection = @Translation("Sitemaps"),
 *   label_singular = @Translation("sitemap"),
 *   label_plural = @Translation("sitemaps"),
 *   label_count = @PluralTranslation(
 *     singular = "@count sitemap",
 *     plural = "@count sitemaps",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\simple_sitemap\Entity\SimpleSitemapStorage",
 *     "list_builder" = "\Drupal\simple_sitemap\SimpleSitemapListBuilder",
 *     "form" = {
 *       "default" = "\Drupal\simple_sitemap\Form\SimpleSitemapEntityForm",
 *       "add" = "\Drupal\simple_sitemap\Form\SimpleSitemapEntityForm",
 *       "edit" = "\Drupal\simple_sitemap\Form\SimpleSitemapEntityForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *   },
 *   config_prefix = "sitemap",
 *   admin_permission = "administer sitemap settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "weight" = "weight",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "type",
 *     "weight",
 *     "status"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/search/simplesitemap/variants/add",
 *     "edit-form" = "/admin/config/search/simplesitemap/variants/{simple_sitemap}",
 *     "delete-form" = "/admin/config/search/simplesitemap/variants/{simple_sitemap}/delete",
 *     "collection" = "/admin/config/search/simplesitemap",
 *   },
 * )
 */
class SimpleSitemap extends ConfigEntityBase implements SimpleSitemapInterface {

  public const SITEMAP_UNPUBLISHED = 0;
  public const SITEMAP_PUBLISHED = 1;
  public const SITEMAP_PUBLISHED_GENERATING = 2;

  public const FETCH_BY_STATUS_ALL = NULL;
  public const FETCH_BY_STATUS_UNPUBLISHED = 0;
  public const FETCH_BY_STATUS_PUBLISHED = 1;

  /**
   * The fetch status.
   *
   * @var int|null
   */
  protected $fetchByStatus;

  /**
   * The sitemap type entity.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface|null
   */
  protected $sitemapType;

  /**
   * Implements the magic __toString() method.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('config', $this->getType()->getConfigDependencyName());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fromPublished(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_PUBLISHED;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fromUnpublished(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_UNPUBLISHED;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fromPublishedAndUnpublished(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_ALL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): SimpleSitemapTypeInterface {
    if ($this->sitemapType === NULL) {
      $this->sitemapType = $this->entityTypeManager()->getStorage('simple_sitemap_type')->load($this->get('type'));
    }

    return $this->sitemapType;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(?int $delta = NULL): string {
    $status = $this->fetchByStatus ?? self::FETCH_BY_STATUS_PUBLISHED;
    $storage = $this->entityTypeManager()->getStorage('simple_sitemap');

    if ($delta) {
      try {
        return $storage->getChunk($this, $status, $delta);
      }
      catch (SitemapNotExistsException $e) {
      }
    }

    if ($storage->hasIndex($this, $status)) {
      return $storage->getIndex($this, $status);
    }

    try {
      return $storage->getChunk($this, $status);
    }
    catch (SitemapNotExistsException $e) {
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function publish(): SimpleSitemapInterface {
    $this->entityTypeManager()->getStorage('simple_sitemap')->publish($this);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteContent(): SimpleSitemapInterface {
    $this->entityTypeManager()->getStorage('simple_sitemap')->deleteContent($this);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addChunk(array $links): SimpleSitemapInterface {
    // @todo Automatically set variant.
    $xml = $this->getType()->getSitemapGenerator()->setSitemap($this)->getChunkContent($links);
    $this->entityTypeManager()->getStorage('simple_sitemap')->addChunk($this, $xml, count($links));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function generateIndex(): SimpleSitemapInterface {
    if ($this->isIndexable()) {
      // @todo Automatically set variant.
      $xml = $this->getType()->getSitemapGenerator()->setSitemap($this)->getIndexContent();
      $this->entityTypeManager()->getStorage('simple_sitemap')->generateIndex($this, $xml);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChunkCount(): int {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->getChunkCount($this, $this->fetchByStatus);
  }

  /**
   * {@inheritdoc}
   */
  public function hasIndex(): bool {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->hasIndex($this, $this->fetchByStatus);
  }

  /**
   * Returns whether the sitemap needs a chunk index.
   *
   * This is not about indexing sitemap variants, it's about creating an index
   * of all sitemap chunks. A sitemap needs a chunk index if it consists of more
   * than one (unpublished) chunk.
   *
   * @return bool
   *   TRUE if the sitemap is indexable and FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function isIndexable(): bool {
    try {
      $this->entityTypeManager()->getStorage('simple_sitemap')->getChunk($this, self::FETCH_BY_STATUS_UNPUBLISHED, 2);
      return TRUE;
    }
    catch (SitemapNotExistsException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex(): string {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->getIndex($this, $this->fetchByStatus);
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return parent::status();
  }

  /**
   * {@inheritdoc}
   */
  public function status(): bool {
    return $this->isEnabled() && $this->contentStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function contentStatus(): int {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->status($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreated(): ?int {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->getCreated($this, $this->fetchByStatus);
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkCount(): int {
    return $this->entityTypeManager()->getStorage('simple_sitemap')->getLinkCount($this, $this->fetchByStatus);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel !== 'canonical') {
      return parent::toUrl($rel, $options);
    }

    $parameters = isset($options['delta']) ? ['page' => $options['delta']] : [];
    unset($options['delta']);

    if (empty($options['base_url'])) {
      /** @var \Drupal\simple_sitemap\Settings $settings */
      $settings = \Drupal::service('simple_sitemap.settings');
      $options['base_url'] = $settings->get('base_url') ?: $GLOBALS['base_url'];
    }

    // Instead of setting the $options['language'] to
    // LanguageInterface::LANGCODE_NOT_APPLICABLE, we disable path processing
    // because of a core change introduced in
    // https://www.drupal.org/project/drupal/issues/2883450.
    // See https://www.drupal.org/project/simple_sitemap/issues/3369919.
    $options['path_processing'] = FALSE;

    return $this->isDefault()
      ? Url::fromRoute(
        'simple_sitemap.sitemap_default',
        $parameters,
        $options)
      : Url::fromRoute(
        'simple_sitemap.sitemap_variant',
        $parameters + ['variant' => $this->id()],
        $options);
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault(): bool {
    /** @var \Drupal\simple_sitemap\Settings $settings */
    $settings = \Drupal::service('simple_sitemap.settings');
    return $this->id() === $settings->get('default_variant');
  }

  /**
   * {@inheritdoc}
   */
  public function isMultilingual(): bool {
    if (!\Drupal::moduleHandler()->moduleExists('language')) {
      return FALSE;
    }

    $url_negotiation_method_enabled = FALSE;
    /** @var \Drupal\language\LanguageNegotiatorInterface $language_negotiator */
    $language_negotiator = \Drupal::service('language_negotiator');
    foreach ($language_negotiator->getNegotiationMethods(LanguageInterface::TYPE_URL) as $method) {
      if ($language_negotiator->isNegotiationMethodEnabled($method['id'])) {
        $url_negotiation_method_enabled = TRUE;
        break;
      }
    }

    /** @var \Drupal\simple_sitemap\Settings $settings */
    $settings = \Drupal::service('simple_sitemap.settings');
    $has_multiple_indexable_languages = count(
        array_diff_key($this->languageManager()->getLanguages(),
          $settings->get('excluded_languages', []))
      ) > 1;

    return $url_negotiation_method_enabled && $has_multiple_indexable_languages;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    if ($property_name === 'type') {
      $this->sitemapType = NULL;
    }

    return parent::set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public static function purgeContent(?array $variants = NULL, ?int $status = self::FETCH_BY_STATUS_ALL) {
    \Drupal::entityTypeManager()->getStorage('simple_sitemap')->purgeContent($variants, $status);
  }

}
