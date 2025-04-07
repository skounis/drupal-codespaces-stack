<?php

namespace Drupal\sitemap\EventSubscriber;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter routes provided by this module, i.e.: to add a custom sitemap path.
 */
class RouteSubscriber extends RouteSubscriberBase implements ContainerInjectionInterface {

  /**
   * A configuration object for the sitemap settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->config = $container->get('config.factory')->get('sitemap.settings');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Note the string 'sitemap' in the next line is the default path for the
    // sitemap.
    $path = $this->config->get('path') ?? 'sitemap';
    if (\is_string($path)) {
      // Create the route to display the sitemap at.
      $collection->add('sitemap.page', new Route($path, [
        '_controller' => '\Drupal\sitemap\Controller\SitemapController::buildSitemap',
        '_title_callback' => '\Drupal\sitemap\Controller\SitemapController::getTitle',
      ], [
        '_permission' => 'access sitemap',
      ]));
    }
  }

}
