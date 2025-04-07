<?php

namespace Drupal\scheduler\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Defines a theme negotiator for the Scheduler routes.
 */
class SchedulerThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Use the Scheduler theme negotiator for scheduler views on the user page.
    $user_page_routes = \Drupal::service('scheduler.manager')->getUserPageViewRoutes();
    $applies = (in_array($route_match->getRouteName(), $user_page_routes));
    return $applies;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    $config = \Drupal::service('config.factory')->getEditable('system.theme');
    $admin_theme = $config->get('admin');
    // Return the admin theme only if the user has permission to use it.
    if (\Drupal::currentUser()->hasPermission('view the administration theme')) {
      return $admin_theme;
    }
  }

}
