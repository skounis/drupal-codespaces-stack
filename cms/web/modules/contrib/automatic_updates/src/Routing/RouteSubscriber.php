<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Routing;

use Drupal\automatic_updates\Form\UpdaterForm;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Modifies route definitions.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Try to run after other route subscribers, to minimize the chances of
      // conflicting with other code that is modifying Update module routes.
      RoutingEvents::ALTER => ['onAlterRoutes', -1000],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Disable status checks on certain routes.
    $disabled_routes = [
      'system.theme_install',
      'update.module_install',
      'update.status',
      'update.report_install',
      'system.status',
      'update.confirmation_page',
      'system.batch_page.html',
    ];
    foreach ($disabled_routes as $route) {
      $route = $collection->get($route);
      if ($route) {
        $route->setOption('_automatic_updates_status_messages', 'skip');
      }
    }

    // Take over the routes defined by the core Update module.
    $update_module_routes = [
      'update.report_update',
      'update.module_update',
      'update.theme_update',
    ];
    $defaults = [
      '_form' => UpdaterForm::class,
      '_title' => 'Update',
    ];
    // Completely redefine the access requirements to disable incompatible
    // requirements defined on the core routes, like `_access_update_manager`,
    // which would allow access to our forms if the `allow_authorize_operations`
    // setting is enabled.
    $requirements = [
      '_permission' => 'administer software updates',
    ];
    $options = [
      '_admin_route' => TRUE,
      '_maintenance_access' => TRUE,
      '_automatic_updates_status_messages' => 'skip',
    ];
    foreach ($update_module_routes as $name) {
      $route = $collection->get($name);
      if ($route) {
        $collection->add($name, new Route($route->getPath(), $defaults, $requirements, $options));
      }
    }
  }

}
