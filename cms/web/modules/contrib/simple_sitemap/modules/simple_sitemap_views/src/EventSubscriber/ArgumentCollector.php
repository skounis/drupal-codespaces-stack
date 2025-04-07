<?php

namespace Drupal\simple_sitemap_views\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Collect information about views arguments.
 */
class ArgumentCollector implements EventSubscriberInterface {

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
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * ArgumentCollector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The currently active route match object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SimpleSitemapViews $sitemap_views, RouteMatchInterface $route_match) {
    $this->viewStorage = $entity_type_manager->getStorage('view');
    $this->sitemapViews = $sitemap_views;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::TERMINATE] = 'onTerminate';
    return $events;
  }

  /**
   * Collect information about views arguments.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   Object of event after a response was sent.
   */
  public function onTerminate(TerminateEvent $event) {
    // Only successful requests are interesting.
    // Collect information about arguments only if views support is enabled.
    if (!$event->getResponse()->isSuccessful() || !$this->sitemapViews->isEnabled()) {
      return;
    }

    $view_id = $this->routeMatch->getParameter('view_id');
    if ($view_id && $view_entity = $this->viewStorage->load($view_id)) {
      $display_id = $this->routeMatch->getParameter('display_id');

      // Get a set of view arguments and try to add them to the index.
      /** @var \Drupal\views\ViewEntityInterface $view_entity */
      $view = $view_entity->getExecutable();
      $args = $this->getViewArgumentsFromRoute();
      $this->sitemapViews->addArgumentsToIndex($view, $args, $display_id);

      // Destroy a view instance.
      $view->destroy();
    }
  }

  /**
   * Get view arguments from current route.
   *
   * @return array
   *   View arguments array.
   */
  protected function getViewArgumentsFromRoute() {
    // The code of this function is taken in part from the view page controller
    // method (Drupal\views\Routing\ViewPageController::handle()).
    $route = $this->routeMatch->getRouteObject();
    $map = $route->hasOption('_view_argument_map') ? $route->getOption('_view_argument_map') : [];

    $args = [];
    foreach ($map as $attribute => $parameter_name) {
      $parameter_name = $parameter_name ?? $attribute;
      $arg = $this->routeMatch->getRawParameter($parameter_name);

      if ($arg !== NULL) {
        $args[] = $arg;
      }
    }

    return $args;
  }

}
