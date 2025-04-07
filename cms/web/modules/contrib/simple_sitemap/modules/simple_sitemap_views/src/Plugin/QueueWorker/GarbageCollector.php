<?php

namespace Drupal\simple_sitemap_views\Plugin\QueueWorker;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes garbage collection in the simple_sitemap_views table.
 *
 * @QueueWorker(
 *   id = "simple_sitemap.views.garbage_collector",
 *   title = @Translation("Garbage collection in the simple_sitemap_views table"),
 *   cron = {"time" = 30}
 * )
 */
class GarbageCollector extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * View entities storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $viewStorage;

  /**
   * Views sitemap data.
   *
   * @var \Drupal\simple_sitemap_views\SimpleSitemapViews
   */
  protected $sitemapViews;

  /**
   * GarbageCollector constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, SimpleSitemapViews $sitemap_views) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->viewStorage = $entity_type_manager->getStorage('view');
    $this->sitemapViews = $sitemap_views;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.views')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $view_id = $data['view_id'];
    /** @var \Drupal\views\ViewEntityInterface|null $view_entity */
    $view_entity = $this->viewStorage->load($view_id);
    $display_ids = [];

    // Check that the view exists and it is enabled.
    if ($view_entity && $view_entity->status()) {
      $view = $view_entity->getExecutable();

      foreach ($this->sitemapViews->getRouterDisplayIds($view_entity) as $display_id) {
        // Ensure the display was correctly set.
        // Check that the display is enabled.
        if (!$view->setDisplay($display_id) || !$view->display_handler->isEnabled()) {
          continue;
        }

        $sitemaps = $this->sitemapViews->getIndexableSitemaps($view);
        $sitemaps = array_keys($sitemaps);

        $args_ids = [];
        foreach ($sitemaps as $sitemap) {
          $sitemap_args_ids = $this->sitemapViews->getIndexableArguments($view, $sitemap);

          if (count($sitemap_args_ids) > count($args_ids)) {
            $args_ids = $sitemap_args_ids;
          }
        }

        // Check that the display has indexable arguments.
        if (empty($args_ids)) {
          continue;
        }

        $display_ids[] = $display_id;

        // Delete records about sets of arguments that are no longer indexed.
        $args_ids = $this->sitemapViews->getArgumentsStringVariations($args_ids);
        $condition = Database::getConnection()->condition('AND');
        $condition->condition('view_id', $view_id);
        $condition->condition('display_id', $display_id);
        $condition->condition('arguments_ids', $args_ids, 'NOT IN');
        $this->sitemapViews->removeArgumentsFromIndex($condition);

        $max_links = 0;
        foreach ($sitemaps as $sitemap) {
          $settings = $this->sitemapViews->getSitemapSettings($view, $sitemap);
          $sitemap_max_links = is_numeric($settings['max_links']) ? $settings['max_links'] : 0;

          if ($sitemap_max_links == 0) {
            $max_links = 0;
            break;
          }

          if ($sitemap_max_links > $max_links) {
            $max_links = $sitemap_max_links;
          }
        }

        // Check if the records limit for display is exceeded.
        if ($max_links > 0) {
          $condition = Database::getConnection()->condition('AND');
          $condition->condition('view_id', $view_id);
          $condition->condition('display_id', $display_id);

          // Delete records that exceed the limit.
          if ($index_id = $this->sitemapViews->getIndexIdByPosition($max_links, $condition)) {
            $condition->condition('id', $index_id, '>');
            $this->sitemapViews->removeArgumentsFromIndex($condition);
          }
        }
      }

      // Delete records about view displays that do not exist or are disabled.
      if (!empty($display_ids)) {
        $condition = Database::getConnection()->condition('AND');
        $condition->condition('view_id', $view_id);
        $condition->condition('display_id', $display_ids, 'NOT IN');
        $this->sitemapViews->removeArgumentsFromIndex($condition);
      }

      // Destroy a view instance.
      $view->destroy();
    }

    // Delete records about the view, if it does not exist, is disabled or it
    // does not have a display whose arguments are indexed.
    if (empty($display_ids)) {
      $condition = Database::getConnection()->condition('AND');
      $condition->condition('view_id', $view_id);
      $this->sitemapViews->removeArgumentsFromIndex($condition);
    }
  }

}
