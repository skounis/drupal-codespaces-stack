<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Manager\EntityManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the menu link URL generator.
 *
 * @UrlGenerator(
 *   id = "entity_menu_link_content",
 *   label = @Translation("Menu link URL generator"),
 *   description = @Translation("Generates menu link URLs by overriding the 'entity' URL generator."),
 *   settings = {
 *     "overrides_entity_type" = "menu_link_content",
 *   },
 * )
 */
class EntityMenuLinkContentUrlGenerator extends EntityUrlGeneratorBase {

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The simple_sitemap.entity_manager service.
   *
   * @var \Drupal\simple_sitemap\Manager\EntityManager
   */
  protected $entitiesManager;

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * EntityMenuLinkContentUrlGenerator constructor.
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
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Manager\EntityManager $entities_manager
   *   The simple_sitemap.entity_manager service.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Logger $logger,
    Settings $settings,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityHelper $entity_helper,
    EntityManager $entities_manager,
    MenuLinkTreeInterface $menu_link_tree,
    MenuLinkManagerInterface $menu_link_manager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger,
      $settings,
      $language_manager,
      $entity_type_manager,
      $entity_helper
    );
    $this->entitiesManager = $entities_manager;
    $this->menuLinkTree = $menu_link_tree;
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.entity_manager'),
      $container->get('menu.link_tree'),
      $container->get('plugin.manager.menu.link')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets(): array {
    $data_sets = [];
    $bundle_settings = $this->entitiesManager
      ->setSitemaps($this->sitemap)
      ->getAllBundleSettings();
    if (!empty($bundle_settings[$this->sitemap->id()]['menu_link_content'])) {
      foreach ($bundle_settings[$this->sitemap->id()]['menu_link_content'] as $bundle_name => $settings) {
        if (!empty($settings['index'])) {

          // Retrieve the expanded tree.
          $tree = $this->menuLinkTree->load($bundle_name, new MenuTreeParameters());
          $tree = $this->menuLinkTree->transform($tree, [
            ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
            ['callable' => 'menu.default_tree_manipulators:flatten'],
          ]);

          foreach ($tree as $item) {
            $data_sets[] = $item->link->getPluginId();
          }
        }
      }
    }

    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set): array {
    try {
      $data_set = $this->menuLinkManager->createInstance($data_set);
    }
    catch (PluginException $e) {
      throw new SkipElementException();
    }

    /** @var \Drupal\Core\Menu\MenuLinkInterface $data_set */
    if (!$data_set->isEnabled()) {
      throw new SkipElementException();
    }

    $url = $data_set->getUrlObject();

    // Do not include external paths.
    if ($url->isExternal()) {
      throw new SkipElementException();
    }

    // If not a menu_link_content link, use bundle settings.
    $meta_data = $data_set->getMetaData();
    if (empty($meta_data['entity_id'])) {
      $entity_settings = $this->entitiesManager
        ->setSitemaps($this->sitemap)
        ->getBundleSettings('menu_link_content', $data_set->getMenuName());
    }

    // If menu link is of entity type menu_link_content, take under account its
    // entity override.
    else {
      $entity_settings = $this->entitiesManager
        ->setSitemaps($this->sitemap)
        ->getEntityInstanceSettings('menu_link_content', $meta_data['entity_id']);
    }
    if (empty($entity_settings[$this->sitemap->id()]['index'])) {
      throw new SkipElementException();
    }

    $entity_settings = $entity_settings[$this->sitemap->id()];

    return $this->constructPathData($url, $entity_settings);
  }

}
