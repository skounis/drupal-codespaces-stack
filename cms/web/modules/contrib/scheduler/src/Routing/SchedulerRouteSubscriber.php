<?php

namespace Drupal\scheduler\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Scheduler route subscriber to add custom access for user views.
 */
class SchedulerRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Access is controlled via \Drupal\scheduler\Access\SchedulerRouteAccess
    // and originally this was specified using $route->getRequirements() and
    // setting $requirements['_custom_access'] = the class.
    // However, we need scheduler.manager as an argument (to satisfy dependency
    // injection) so now this is provided by a service scheduler.access_check.
    $user_page_routes = \Drupal::service('scheduler.manager')->getUserPageViewRoutes();
    foreach ($user_page_routes as $user_route) {
      if ($route = $collection->get($user_route)) {
        $route->setRequirement('_custom_access', 'scheduler.access_check::access');
      }
    }
  }

}
