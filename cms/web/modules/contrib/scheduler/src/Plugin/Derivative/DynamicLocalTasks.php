<?php

namespace Drupal\scheduler\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks.
 *
 * The local tasks that define tabs for the 'Scheduled' entity views cannot be
 * hard-coded in the links.task.yml file because if a view is disabled its route
 * will not exist and this produces an exception "Route X does not exist." The
 * routes are defined here instead to enable checking that the views are loaded.
 */
class DynamicLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a DynamicLocalTasks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    $view_storage = $this->entityTypeManager->getStorage('view');

    // Define a local task for scheduled content (nodes) view, only when the
    // view can be loaded, is enabled and that the overview display exists.
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $view_storage->load('scheduler_scheduled_content');
    if ($view && $view->status() && $view->getDisplay('overview')) {
      // The content overview has weight 0 and moderated content has weight 1
      // so use weight 5 for the scheduled content tab.
      $this->derivatives['scheduler.scheduled_content'] = [
        'title' => $this->t('Scheduled content'),
        'route_name' => 'view.scheduler_scheduled_content.overview',
        'parent_id' => 'system.admin_content',
        'weight' => 5,
      ] + $base_plugin_definition;

      // Core content_moderation module defines an 'overview' local task which
      // is required when adding additional local tasks. If that module is not
      // installed then define the tab here. This can be removed if
      // https://www.drupal.org/project/drupal/issues/3199682 gets committed.
      // See also scheduler_local_tasks_alter().
      $this->derivatives['scheduler.content_overview'] = [
        'title' => $this->t('Overview'),
        'route_name' => 'system.admin_content',
        'parent_id' => 'system.admin_content',
      ] + $base_plugin_definition;
    }

    $view = $view_storage->load('scheduler_scheduled_media');
    if ($view && $view->status() && $view->getDisplay('overview')) {
      // Define local task for scheduled media view.
      $this->derivatives['scheduler.scheduled_media'] = [
        'title' => $this->t('Scheduled media'),
        'route_name' => 'view.scheduler_scheduled_media.overview',
        'parent_id' => 'entity.media.collection',
        // Media Library table tab is weight 10 and grid is 20, so use 30.
        'weight' => 30,
      ] + $base_plugin_definition;

      // This task is added so that we get an 'overview' sub-task link alongside
      // the 'scheduled media' sub-task link.
      $this->derivatives['scheduler.media_overview'] = [
        'title' => $this->t('Overview'),
        'route_name' => 'entity.media.collection',
        'parent_id' => 'entity.media.collection',
      ] + $base_plugin_definition;
    }

    $view = $view_storage->load('scheduler_scheduled_commerce_product');
    if ($view && $view->status() && $view->getDisplay('overview')) {
      // The page created by route entity.commerce_product.collection does not
      // have any tabs or sub-links, because the Commerce Product module does
      // not specify any local tasks for this route. Therefore we need a
      // top-level task which just defines the route name as a base route. This
      // will be used as the parent for the two tabs defined below.
      $this->derivatives['scheduler.commerce_products'] = [
        'route_name' => 'entity.commerce_product.collection',
        'base_route' => 'entity.commerce_product.collection',
      ] + $base_plugin_definition;

      // Define local task for the scheduled products view.
      $this->derivatives['scheduler.scheduled_products'] = [
        'title' => $this->t('Scheduled products'),
        'route_name' => 'view.scheduler_scheduled_commerce_product.overview',
        'parent_id' => 'scheduler.local_tasks:scheduler.commerce_products',
        'weight' => 5,
      ] + $base_plugin_definition;

      // This task is added so that we get an 'overview' sub-task link alongside
      // the 'scheduled products' sub-task link.
      $this->derivatives['scheduler.commerce_product.collection'] = [
        'title' => $this->t('Overview'),
        'route_name' => 'entity.commerce_product.collection',
        'parent_id' => 'scheduler.local_tasks:scheduler.commerce_products',
      ] + $base_plugin_definition;
    }

    $view = $view_storage->load('scheduler_scheduled_taxonomy_term');
    if ($view && $view->status() && $view->getDisplay('overview')) {
      // In the same manner as for Commerce Products the page created by route
      // entity.taxonomy_vocabulary.collection does not have tabs or sub-links,
      // so we need to define one with a route name and base route here, to be
      // used as the parent for the two tabs defined below.
      $this->derivatives['scheduler.taxonomy_collection'] = [
        'route_name' => 'entity.taxonomy_vocabulary.collection',
        'base_route' => 'entity.taxonomy_vocabulary.collection',
      ] + $base_plugin_definition;

      // Define local task for the scheduled taxonomy terms view.
      $this->derivatives['scheduler.scheduled_taxonomy_terms'] = [
        'title' => $this->t('Scheduled terms'),
        'route_name' => 'view.scheduler_scheduled_taxonomy_term.overview',
        'parent_id' => 'scheduler.local_tasks:scheduler.taxonomy_collection',
        'weight' => 5,
      ] + $base_plugin_definition;

      // This task is added so that we get an 'overview' sub-task link alongside
      // the 'scheduled taxonomy terms' sub-task link.
      $this->derivatives['scheduler.taxonomy_vocabulary.collection'] = [
        'title' => $this->t('Overview'),
        'route_name' => 'entity.taxonomy_vocabulary.collection',
        'parent_id' => 'scheduler.local_tasks:scheduler.taxonomy_collection',
      ] + $base_plugin_definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
