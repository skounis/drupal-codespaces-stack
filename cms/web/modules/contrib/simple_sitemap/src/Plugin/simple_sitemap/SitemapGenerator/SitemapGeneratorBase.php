<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for SitemapGenerator plugins.
 */
abstract class SitemapGeneratorBase extends SimpleSitemapPluginBase implements SitemapGeneratorInterface {

  protected const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * Sitemap XML writer.
   *
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter
   */
  protected $writer;

  /**
   * The sitemap entity.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface
   */
  protected $sitemap;

  /**
   * The extension.list.module service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * An array of index attributes.
   *
   * @var array
   */
  protected static $indexAttributes = [
    'xmlns' => self::XMLNS,
  ];

  /**
   * SitemapGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter $sitemap_writer
   *   Sitemap XML writer.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The extension.list.module service.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $language_manager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleHandlerInterface $module_handler,
    SitemapWriter $sitemap_writer,
    Settings $settings,
    ModuleExtensionList $module_list,
    ?LanguageManagerInterface $language_manager = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->writer = $sitemap_writer;
    $this->settings = $settings;
    $this->moduleList = $module_list;
    if ($language_manager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $language_manager argument is deprecated in simple_sitemap:4.2.2 and will be required in simple_sitemap:5.0.0. See https://www.drupal.org/project/simple_sitemap/issues/3340003', E_USER_DEPRECATED);
      // @phpstan-ignore-next-line
      $language_manager = \Drupal::languageManager();
    }
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('simple_sitemap.sitemap_writer'),
      $container->get('simple_sitemap.settings'),
      $container->get('extension.list.module'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setSitemap(SimpleSitemapInterface $sitemap): SitemapGeneratorInterface {
    $this->sitemap = $sitemap;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getChunkContent(array $links): string;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getIndexContent(): string {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    $this->addXslUrl();
    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    $this->moduleHandler->alter('simple_sitemap_index_attributes', $attributes, $this->sitemap);
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    // Add sitemap chunk locations to document.
    for ($delta = 1; $delta <= $this->sitemap->fromUnpublished()->getChunkCount(); $delta++) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', $this->sitemap->toUrl('canonical', ['delta' => $delta])->toString());
      // @todo Should this be current time instead?
      $this->writer->writeElement('lastmod', date('c', $this->sitemap->fromUnpublished()->getCreated()));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

  /**
   * Adds the XML stylesheet.
   */
  protected function addXslUrl(): void {
    if ($this->settings->get('xsl')) {
      $this->writer->writeXsl($this->getPluginId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getXslContent(): ?string {
    return NULL;
  }

}
