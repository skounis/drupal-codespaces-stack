<?php

namespace Drupal\sitemap\Plugin\Sitemap;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\sitemap\SitemapBase;
use Drupal\system\Entity\Menu as MenuEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a sitemap for an individual menu.
 *
 * @Sitemap(
 *   id = "menu",
 *   title = @Translation("Menu name"),
 *   description = @Translation("Menu description"),
 *   settings = {
 *     "title" = NULL,
 *     "show_disabled" = FALSE,
 *   },
 *   deriver = "Drupal\sitemap\Plugin\Derivative\MenuSitemapDeriver",
 *   enabled = FALSE,
 *   menu = "",
 * )
 */
class Menu extends SitemapBase {

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * A service to load, transform, and render menu link trees.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  protected MenuLinkTree $menuLinkTree;

  /**
   * A module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->menuLinkTree = $container->get('sitemap.menu.link_tree');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Provide the menu name as the default title.
    $menu_name = $this->getPluginDefinition()['menu'];
    $menu = $this->entityTypeManager->getStorage('menu')->load($menu_name);
    $form['title']['#default_value'] = $this->settings['title'] ?? $menu->label();

    $form['show_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show disabled menu items'),
      '#default_value' => $this->settings['show_disabled'] ?? FALSE,
      '#description' => $this->t('When selected, disabled menu links will also be shown.<br><strong>Warning</strong>: Showing disabled menu links will reveal information that would normally require the sitemap viewer to have the %permission permission!', [
        '%permission' => $this->t('Administer menus and menu links'),
      ]),
      '#access' => $this->currentUser->hasPermission('show disabled menu items on sitemap'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $menu_id = $this->pluginDefinition['menu'];
    $menu = MenuEntity::load($menu_id);
    // Retrieve the expanded tree.
    $parameters = new MenuTreeParameters();
    if (!$this->settings['show_disabled']) {
      $parameters->onlyEnabledLinks();
    }

    $tree = $this->menuLinkTree->load($menu_id, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    // Add an alter hook so that other modules can manipulate the
    // menu tree prior to rendering.
    // @todo Document
    $alter_mid = preg_replace('/[^a-z0-9_]+/', '_', $menu_id);
    $this->moduleHandler->alter([
      'sitemap_menu_tree', 'sitemap_menu_tree_' . $alter_mid,
    ], $tree, $menu);

    $menu_display = $this->menuLinkTree->build($tree);

    return ($tree) ? [
      '#theme' => 'sitemap_item',
      '#title' => $this->settings['title'],
      '#content' => $menu_display,
      '#sitemap' => $this,
    ] : [];
  }

}
